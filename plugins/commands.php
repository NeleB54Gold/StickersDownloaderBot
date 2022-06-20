<?php

# Private chat commands
if ($v->chat_type == 'private') {
	# Get Stickers Set by url or text_link entities
	if (isset($v->entities) && !empty($v->entities)) {
		foreach ($v->entities as $entity) {
			if ($entity['type'] == 'url') {
				$url = substr($v->text, $entity['offset'], $entity['length']);
			} elseif ($entity['type'] == 'text_link') {
				$url = $entity['url'];
			}
			if (strpos($url, 'http://t.me/addstickers/') === 0) {
				$urlname = str_replace('http://t.me/addstickers/', '', $url);
				if ($urlname) $v->sticker_set = $urlname;
			} elseif (strpos($url, 'https://t.me/addstickers/') === 0) {
				$urlname = str_replace('https://t.me/addstickers/', '', $url);
				if ($urlname) $v->sticker_set = $urlname;
			}
		}
	}
	# Start command
	if ($v->command == 'start') {
		if ($bot->configs['database']['status'] && $user['status'] !== 'started') $db->setStatus($v->user_id, 'started');
		$uscount = $db->query('SELECT COUNT(id) FROM users', [], 1)['COUNT(id)'];
		$dlcount = file_get_contents('download-count.txt');
		$t = $bot->bold('✅ Send me a sticker to export the whole Sticker Pack!') . 
		PHP_EOL . PHP_EOL . '⬇️ Downloaded: ' . round($dlcount) .
		PHP_EOL . '👥 Users: ' . round($uscount) .
		PHP_EOL . '💬 @NeleBots';
		$bot->sendMessage($v->chat_id, $t, $buttons);
	}
	# Sticker informations
	elseif ($v->sticker_id || $v->sticker_set && !$v->query_data) {
		$set = $bot->getStickers($v->sticker_set);
		if ($set['ok']) {
			$emoji = ['❌', '✅'];
			$sticker_url = 'https://t.me/addstickers/' . $set['result']['name'];
			$t = $bot->bold('Name: ') . $bot->code($set['result']['title'], 1) .
			PHP_EOL . $bot->bold('Link: ') . $bot->text_link($set['result']['name'], $sticker_url) .
			PHP_EOL . $bot->bold('Contains animated: ') . $emoji[round($set['result']['is_animated'])] .
			PHP_EOL . $bot->bold('Contains video: ') . $emoji[round($set['result']['is_video'])] .
			PHP_EOL . $bot->bold('Contains masks: ') . $emoji[round($set['result']['contains_masks'])] .
			PHP_EOL . $bot->bold('Stickers: ') . $bot->code(round(count($set['result']['stickers'])));
			$buttons[] = [
				$bot->createInlineButton('⬇️ Download pack', 'dl'),
				$bot->createInlineButton('➕ Add', $sticker_url, 'url')
			];
			$buttons[][] = $bot->createInlineButton('🖼 Download sticker', 'dl-single');
		} else {
			$t = $bot->italic('Failed to get that pack...');
		}
		$bot->sendMessage($v->chat_id, $t, $buttons, 'def', 'def', $v->message_id);
	} 
	# Download pack
	elseif ($v->query_data == 'dl') {
		if ($configs['redis']['status'] && $db->rget('NeleBotX-' . $v->user_id . '-StickersDL'))  {
			$bot->answerCBQ($v->query_id, '⚠️ Wait for the previous downloads to complete...', 1);
			die;
		}
		$bot->answerCBQ($v->query_id, '⬇️ Downloading...');
		if ($configs['redis']['status'] && $pack = json_decode($db->rget('Stickers-' . $v->sticker_set), true) && $pack['file_id']) {
			$zip_name = 'cache/' . $v->sticker_set . ' @StickersDownloaderBot.zip';
			$thumb_name = 'cache/' . $v->sticker_set . '_thumb.png';
			$t = $bot->bold('📲 Download of ' . $v->sticker_set, 1) . PHP_EOL . PHP_EOL . '⬇️ Downloading...';
			$bot->editText($v->chat_id, $v->message_id, $t);
			$file = $bot->getFile($pack['file_id']);
			if ($file['ok']) copy($bot->configs['telegram_bot_api'] . '/file/bot' . $bot->token . '/' . $file['result']['file_path'], $zip_name);
			if ($pack['thumb']) {
				$file = $bot->getFile($pack['thumb']);
				if ($file['ok']) copy($bot->configs['telegram_bot_api'] . '/file/bot' . $bot->token . '/' . $file['result']['file_path'], $thumb_name);
			}
			if (!file_exists($thumb_name)) $thumb_name = 'https://telegra.ph/file/0c5c55e503969b72c18a5.jpg';
			$buttons[][] = $bot->createInlineButton('⭐ Vote this Bot', 'https://t.me/BotsArchive/1497', 'url');
			$buttons[][] = $bot->createInlineButton('☕️ Buy me a coffee', 'https://www.paypal.com/donate/?hosted_button_id=3NJZ7EQDFSG7J', 'url');
			$bot->sendDocument($v->chat_id, $bot->createFileInput($zip_name), $bot->bold('🚀 Uploaded by @StickersDownloaderBot'), $buttons, 'def', 0, $bot->createFileInput($thumb_name));
			$bot->editText($v->chat_id, $v->message_id, $v->text, $v->inline_keyboard, $v->entities);
			unlink($zip_name);
			unlink($thumb_name);
			die;
		}
		$set = $bot->getStickers($v->sticker_set);
		if ($configs['redis']['status']) $db->rset('NeleBotX-' . $v->user_id . '-StickersDL', 1, 10);
		if ($set['ok']) {
			$emoji = ['❌', '✅'];
			$sticker_url = 'https://t.me/addstickers/' . $set['result']['name'];
			$t = $bot->bold('📲 Download of ' . $set['result']['title'], 1) . PHP_EOL . PHP_EOL . '⬇️ Downloading...';
			$bot->editText($v->chat_id, $v->message_id, $t);
			$file_names = [];
			$xtime = time() + 1;
			foreach ($set['result']['stickers'] as $sticker) {
				$file = $bot->getFile($sticker['file_id']);
				if ($file['ok']) {
					if ($set['result']['is_video']) {
						$ext = 'webm';
					} elseif ($set['result']['is_animated']) {
						$ext = 'tgs';
					} else {
						$ext = 'webp';
					}
					$file_names[] = $file_name = 'cache/' . $set['result']['name'] . '-' . round($sid) . '.' . $ext;
					copy($bot->configs['telegram_bot_api'] . '/file/bot' . $bot->token . '/' . $file['result']['file_path'], $file_name);
					$sid += 1;
					$sps += 1;
					if ($xtime < time()) {
						$bot->editText($v->chat_id, $v->message_id, $t . round($sps) . ' sticker/second');
						$xtime = time();
						unset($sps);
					}
				}
			}
			$t .= 'done!' . PHP_EOL . '📥 Creating zip...';
			$bot->editText($v->chat_id, $v->message_id, $t);
			$zip = new ZipArchive;
			$zip_name = 'cache/' . $set['result']['name'] . ' @StickersDownloaderBot.zip';
			if ($zip->open($zip_name, ZipArchive::CREATE) !== TRUE) {
				$bot->editText($v->chat_id, $v->message_id, $t . PHP_EOL . $bot->bold('❌ An error has occurred'), $buttons);
				die;
			}
			$xtime = time() + 1;
			foreach ($file_names as $file_name) {
				$zip->addFile($file_name, end(explode('/', $file_name)));
				if ($xtime < time()) {
					$bot->editText($v->chat_id, $v->message_id, $t . round($sps) . ' sticker/second');
					$xtime = time();
					unset($sps);
				}
			}
			$zip->close();
			$t .= 'done!' . PHP_EOL . '⬆️ Uploading...';
			$bot->editText($v->chat_id, $v->message_id, $t);
			$buttons[][] = $bot->createInlineButton('⭐ Vote this Bot', 'https://t.me/BotsArchive/1497', 'url');
			$buttons[][] = $bot->createInlineButton('☕️ Buy me a coffee', 'https://www.paypal.com/donate/?hosted_button_id=3NJZ7EQDFSG7J', 'url');
			$thumb_name = 'cache/' . $set['result']['name'] . '_thumb.png';
			if ($set['result']['thumb']) {
				$file = $bot->getFile($set['result']['thumb']['file_id']);
				if ($file['ok']) {
					copy($bot->configs['telegram_bot_api'] . '/file/bot' . $bot->token . '/' . $file['result']['file_path'], $thumb_name);
				}
			} elseif (!$set['result']['is_animated']) {
				$thumb_name = 'cache/' . $set['result']['name'] . '-0.webp';
			}
			if (!file_exists($thumb_name)) $thumb_name = 'https://telegra.ph/file/0c5c55e503969b72c18a5.jpg';
			$bot->editConfigs('response', true);
			$r = $bot->sendDocument($v->chat_id, $bot->createFileInput($zip_name), $bot->bold('🚀 Uploaded by @StickersDownloaderBot'), $buttons, 'def', 0, $bot->createFileInput($thumb_name));
			if ($configs['redis']['status']) {
				$pack = [
					'file_id' => $r['result']['document']['file_id'],
					'thumb' => $r['result']['document']['thumb']['file_id'],
				];
				$db->rset('Stickers-' . $v->sticker_set, json_encode($pack), 60 * 60);
			}
			$t .= 'done!';
			$bot->editText($v->chat_id, $v->message_id, $v->text, $v->inline_keyboard, $v->entities);
			if ($configs['redis']['status']) $db->rdel('NeleBotX-' . $v->user_id . '-StickersDL');
			file_put_contents('download-count.txt', file_get_contents('download-count.txt') + 1);
			foreach ($file_names as $file_name) {
				unlink($file_name);
			}
			unlink($zip_name);
			unlink($thumb_name);
		} else {
			$t = $bot->italic('❌ Failed to get that pack...');
			$bot->editText($v->chat_id, $v->message_id, $t);
		}
	}
	# Download a single sticker
	elseif ($v->query_data == 'dl-single') {
		$v->varSticker($v->reply_to_message['sticker']);
		if (is_null($v->sticker_id)) {
			$bot->answerCBQ($v->query_id, '⚠️ Sticker to download not found...', 1);
			die;
		} elseif ($configs['redis']['status'] && $db->rget('NeleBotX-' . $v->user_id . '-StickersDL'))  {
			$bot->answerCBQ($v->query_id, '⚠️ Wait for the previous downloads to complete...', 1);
			die;
		}
		$bot->answerCBQ($v->query_id, '⬇️ Downloading...');
		$set = $bot->getStickers($v->sticker_set);
		if ($configs['redis']['status']) $db->rset('NeleBotX-' . $v->user_id . '-StickersDL', 1, 10);
		if ($set['ok']) {
			$file = $bot->getFile($v->sticker_id);
			if ($file['ok']) {
				$ext = ['webp', 'dl-tgs'];
				$file_name = 'cache/' . $v->sticker_set . '-single.' . $ext[round($v->sticker_animated)];
				copy($bot->configs['telegram_bot_api'] . '/file/bot' . $bot->token . '/' . $file['result']['file_path'], $file_name);
				$buttons[][] = $bot->createInlineButton('⭐ Vote this Bot', 'https://t.me/BotsArchive/1497', 'url');
				$buttons[][] = $bot->createInlineButton('☕️ Buy me a coffee', 'https://www.paypal.com/donate/?hosted_button_id=3NJZ7EQDFSG7J', 'url');
				$bot->sendDocument($v->chat_id, $bot->createFileInput($file_name, 'application/x-bad-tgsticker'), $bot->bold('🚀 Uploaded by @StickersDownloaderBot'), $buttons, 'def', 0, 0, 'inline', false, true);
				$bot->editText($v->chat_id, $v->message_id, $v->text, $v->inline_keyboard, $v->entities);
				if ($configs['redis']['status']) $db->rdel('NeleBotX-' . $v->user_id . '-StickersDL');
				file_put_contents('download-count.txt', file_get_contents('download-count.txt') + 1);
				unlink($file_name);
			} else {
				$t = $bot->italic('❌ Failed to get that pack...');
				$bot->editText($v->chat_id, $v->message_id, $t);
			}
		} else {
			$t = $bot->italic('❌ Failed to get that pack...');
			$bot->editText($v->chat_id, $v->message_id, $t);
		}
	}
	# Unknown command
	else {
		$help = PHP_EOL . 'Send a sticker to Download the set.';
		if ($v->command) {
			$t = $bot->bold('😶 Unknown command...') . $help;
		} elseif ($v->query_data) {
			$t = '😶 Unknown button...' . $help;
		} else {
			$t = $bot->bold('💤 Nothing to do...') . $help;
		}
		if ($v->query_id) {
			$bot->answerCBQ($v->query_id, $t);
		} else {
			$bot->sendMessage($v->chat_id, $t);
		}
	}
}
# Leave unsupported chats
elseif (in_array($v->chat_type, ['group', 'supergroup', 'channel'])) {
	$bot->leave($v->chat_id);
}

?>

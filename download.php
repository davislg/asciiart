<?php

$poolSize = 100;
$numRequestsInPool = 0;
$numFinishedFiles = 0;
$mh = curl_multi_init();
$fileHandle = @fopen('domains.txt', 'r');
if ($fileHandle) {

	while(1){

		// Refill pool while its not full and there are lines left in the file. 
		while($numRequestsInPool < $poolSize && ($domain = fgets($fileHandle)) !== false){

			// Check if it's in the cache.
			$cachedFilePath = cachedFilePath($domain);
			if(is_file($cachedFilePath)){

//				print('<br>in cache: '.$domain);
//
//				// Process immediately.
//				handlePage($domain, gzinflate(file_get_contents($cachedFilePath)));

			}else{
				// Initiate download.
	
//				print('<br>added: '.$domain);
				
				// Set up curl to download the frontpage.
				$URL = 'http://www.'.$domain;
				$ch = curl_init($URL);
				curl_setopt_array($ch, array(
					CURLOPT_RETURNTRANSFER	=> true,
					CURLOPT_FOLLOWLOCATION	=> true,
					CURLOPT_MAXREDIRS		=> 3,
					CURLOPT_TIMEOUT			=> 60,
				));
				
				// Remember what domain this handle is downloading.
				$handleToDomain[$ch] = $domain;
		
				// Add the request to the pool.
				curl_multi_add_handle($mh, $ch);
				++$numRequestsInPool;
			}

			flush();
		}
		
		// Wait for data.
		curl_multi_select($mh);

		// Process requests.
//		print('<br>processing: ');
		do {
//			print('*');
			$execReturnValue = curl_multi_exec($mh, $foo);
		} while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);
	
		// Handle finished requests.
		print('<br><br><b>finished:</b>');
		while(false !== $handleInfo = curl_multi_info_read($mh)){

			// Check if the handle is done.
			if($handleInfo['msg'] == CURLMSG_DONE){
				
				$domain = $handleToDomain[$handleInfo['handle']];

				print('<br>'.$domain );

				// Read the page from the handle.
				$pageContent = curl_multi_getcontent($handleInfo['handle']);
				
				// Save the page to disk.
				file_put_contents(cachedFilePath($domain), gzdeflate($pageContent));
				++$numFinishedFiles;

//				handlePage($domain , $pageContent);

				// Remove the handle from the pool.
				curl_multi_remove_handle($mh, $handleInfo['handle']);
				curl_close($handleInfo['handle']);
				unset($handleToDomain[$handleInfo['handle']]);
				--$numRequestsInPool;
			}

			flush();
		}
		print('<br><br>downloaded '.$numFinishedFiles.' files this session.');
	
		// Are we done yet?
		if(feof($fileHandle) && !$numRequestsInPool)
			break;
	}

	curl_multi_close($mh);
	fclose($fileHandle);
	
	print('<br><br>done');
}


function cachedFilePath($domain){

	// Use the first 2 characters of the file name as the dir.
	$dir = 'cache/' . substr($domain, 0, min(2, strpos($domain, '.'))) . '/';

	// Create it if necessary.
	if(!is_dir($dir))
		mkdir($dir);
	
	return $dir . $domain;
}


<?php
	$packetSize = 188;
	// 90Khz for MPEG TS
	$clockFrequency = 90000;

	function parseFrame($singleFrame) {
                $sync_byte = $oneFrame[0];
                if ($debug) echo "==== TRANSPORT STREAM HEADER ====\n";
                if ($sync_byte = 'G')  { if ($debug) echo "8 bits: Sync byte: OK\n"; } else { return false; }

                $byte = decbin(ord($oneFrame[1]));
                $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
                $byte = decbin(ord($oneFrame[2]));
                $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
                if ($debug) echo "1 bit: Transport Error Indicator = " . $bits[0] . "\n";
                if ($debug) echo "1 bit: Payload Unit Start Indicator = " . $bits[1] . "\n";
                if ($debug) echo "1 bit: Transport Priority = " . $bits[2] . "\n";
                if ($debug) echo "13 bits: PID = " . substr($bits, 3, 13) . " (dec: " . bindec(substr($bits, 3, 13)) . ")\n";       

                $byte = decbin(ord($oneFrame[3]));
                $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
                if ($debug) echo "2 bits: Scrambling control = " . $bits[0] . $bits[1] . "\n";
                if ($debug) echo "1 bit: Adaptation field exists = " . $bits[2] . "\n";
                if ($debug) echo "1 bit: Payload exists = " . $bits[3] . "\n";
                if ($debug) echo "4 bits: Continuity counter = " . substr($bits, 4, 4) . " (dec: " . bindec(substr($bits, 4, 4)) . ")\n";   

	        if ($debug) echo "==== PES HEADER ====\n";  
	        $pes_start = substr($oneFrame, 4, 3);
	        if (ord($pes_start[0]) == 0 && ord($pes_start[1]) == 0 && ord($pes_start[2]) == 1) {
	                if ($debug) echo "24 bits: Packet start prefix: OK\n"; 
	        } else {
	                die("No PES header detected, aborting\n");
	        }

	        $stream_id = $oneFrame[7];
	        if ($debug) echo "8 bits: Stream ID = " . ord($stream_id) . " (dec)\n";
	        $byte = decbin(ord($oneFrame[8]));
	        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
	        $byte = decbin(ord($oneFrame[9]));
	        $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
	        if ($debug) echo "16 bits: PES packet length = " . bindec($bits) . " (bytes)\n";
	        $byte = decbin(ord($oneFrame[10]));
	        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
        
	        if($debug) echo "==== OPTIONAL PES HEADER ====\n";
	        if ($bits[0] == '1' && $bits[1] == '0') {
	                if ($debug) echo "2 bits: Optional header marker: OK\n";
	        } else {
	                die("No optional PES header detected, aborting\n");
	        }
	        if ($debug) echo "2 bits: Scrambling control = " . $bits[2] . $bits[3] . "\n";
	        if ($debug) echo "1 bit: Priority = " . $bits[4] . "\n";
	        if ($debug) echo "1 bit: Data alignment indicator = " . $bits[5] . "\n";
	        if ($bits[5] == '1') { if ($debug) echo "** This is start of ID3 content\n"; }
	        if ($debug) echo "1 bit: Copyright = " . $bits[6] . "\n";
	        if ($debug) echo "1 bit: Original or copy = " . $bits[7] . "\n";

	        $byte = decbin(ord($oneFrame[11]));
	        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);

	        if ($debug) echo "2 bits: PTS & DTS indicator = " . $bits[0] . $bits[1] . "\n";
	        if ($bits[0] . $bits[1] == '10') { if ($debug) echo "** Only PTS is expected\n"; }
	        if ($debug) echo "1 bit: ESCR flag = " . $bits[2] . "\n";
	        if ($debug) echo "1 bit: ES rate flag = " . $bits[3] . "\n";
	        if ($debug) echo "1 bit: DSM trick mode flag = " . $bits[4] . "\n";
	        if ($debug) echo "1 bit: Copy info flag = " . $bits[5] . "\n";
	        if ($debug) echo "1 bit: CRC flag = " . $bits[6] . "\n";

	}

	if (empty($argv[1]) || !file_exists($argv[1])) {
		die("Syntax: " . $argv[0] . " <filename>\n");
	} else {
		$filename = $argv[1];
	}

	if (filesize($filename) % 188 != 0) {
		die("Broken MPEG TS stream – filesize must be a power of 188\n");
	}

	$handle = fopen($filename, "r");
	$filePosition = 0;
	$debug = false;
	$frameCounter = 0;

	while(!feof($handle)) {
		fseek($handle, $filePosition);
		if ($debug) echo "Seeking to " . ftell($handle) . "\n";
		$oneFrame = fread($handle, $packetSize);
		if ($debug) echo "Read " . strlen($oneFrame) . " bytes\n";
		if (strlen($oneFrame) != $packetSize) die("Received frame of incorrect size != " . $packetSize . "\n");
		if (parseFrame($oneFrame)) { $frameCounter++; } 


		$sync_byte = $oneFrame[0];
		if ($debug) echo "==== TRANSPORT STREAM HEADER ====\n";
		if ($sync_byte = 'G')  { echo "8 bits: Sync byte: OK\n";  $frameCounter++; }
		$byte = decbin(ord($oneFrame[1]));
		$bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
		$byte = decbin(ord($oneFrame[2]));
		$bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
	        if ($debug) echo "1 bit: Transport Error Indicator = " . $bits[0] . "\n";
	        if ($debug) echo "1 bit: Payload Unit Start Indicator = " . $bits[1] . "\n";
	        if ($debug) echo "1 bit: Transport Priority = " . $bits[2] . "\n";
	        if ($debug) echo "13 bits: PID = " . substr($bits, 3, 13) . " (dec: " . bindec(substr($bits, 3, 13)) . ")\n";       

        	$byte = decbin(ord($oneFrame[3]));
        	$bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
 		echo "2 bits: Scrambling control = " . $bits[0] . $bits[1] . "\n";
 		echo "1 bit: Adaptation field exists = " . $bits[2] . "\n";
	        echo "1 bit: Payload exists = " . $bits[3] . "\n";
	        echo "4 bits: Continuity counter = " . substr($bits, 4, 4) . " (dec: " . bindec(substr($bits, 4, 4)) . ")\n";   

		$filePosition =+ $packetSize;	
	}

	$byte = decbin(ord($oneFrame[3]));
	$bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
	echo "2 bits: Scrambling control = " . $bits[0] . $bits[1] . "\n";
	echo "1 bit: Adaptation field exists = " . $bits[2] . "\n";
	echo "1 bit: Payload exists = " . $bits[3] . "\n";
	echo "4 bits: Continuity counter = " . substr($bits, 4, 4) . " (dec: " . bindec(substr($bits, 4, 4)) . ")\n";	

	echo "==== PES HEADER ====\n";	
	$pes_start = substr($oneFrame, 4, 3);
	if (ord($pes_start[0]) == 0 && ord($pes_start[1]) == 0 && ord($pes_start[2]) == 1) {
		echo "24 bits: Packet start prefix: OK\n"; 
	} else {
		die("No PES header detected, aborting\n");
	}

	$stream_id = $oneFrame[7];
	echo "8 bits: Stream ID = " . ord($stream_id) . " (dec)\n";
	$byte = decbin(ord($oneFrame[8]));
        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
	$byte = decbin(ord($oneFrame[9]));
        $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
	echo "16 bits: PES packet length = " . bindec($bits) . " (bytes)\n";

        $byte = decbin(ord($oneFrame[10]));
        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
	
	echo "==== OPTIONAL PES HEADER ====\n";
	if ($bits[0] == '1' && $bits[1] == '0') {
		echo "2 bits: Optional header marker: OK\n";
	} else {
		die("No optional PES header detected, aborting\n");
	}
	echo "2 bits: Scrambling control = " . $bits[2] . $bits[3] . "\n";
	echo "1 bit: Priority = " . $bits[4] . "\n";
	echo "1 bit: Data alignment indicator = " . $bits[5] . "\n";
	if ($bits[5] == '1') { echo "** This is start of ID3 content\n"; }
	echo "1 bit: Copyright = " . $bits[6] . "\n";
	echo "1 bit: Original or copy = " . $bits[7] . "\n";

        $byte = decbin(ord($oneFrame[11]));
        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);

	echo "2 bits: PTS & DTS indicator = " . $bits[0] . $bits[1] . "\n";
	if ($bits[0] . $bits[1] == '10') { echo "** Only PTS is expected\n"; }
	echo "1 bit: ESCR flag = " . $bits[2] . "\n";
	echo "1 bit: ES rate flag = " . $bits[3] . "\n";
	echo "1 bit: DSM trick mode flag = " . $bits[4] . "\n";
	echo "1 bit: Copy info flag = " . $bits[5] . "\n";
	echo "1 bit: CRC flag = " . $bits[6] . "\n";
	echo "1 bit: Extension flag = " . $bits[7] . "\n";

	echo "8 bits: PES header length = " . ord($oneFrame[12]) . " (bytes)\n";
	$dataStart = 14 + ord($oneFrame[12]) - 1;
	echo "** Data will start at " . $dataStart . " bytes\n";

        $byte = decbin(ord($oneFrame[13]));
        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
	$byte = decbin(ord($oneFrame[14]));
        $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
	$byte = decbin(ord($oneFrame[15]));
        $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
	$byte = decbin(ord($oneFrame[16]));
        $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
	$byte = decbin(ord($oneFrame[17]));
        $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);

	if ($bits[0] . $bits[1] . $bits[2] . $bits[3] == '0010')
		echo "4 bits: PTS padding: OK\n";

	// Extract PTS from the bit pattern
	$pts_bits = substr($bits, 4, 3) . substr($bits, 8, 15) . substr($bits, 24, 15);
	echo "PTS = " . bindec($pts_bits) . " (dec) or " . (bindec($pts_bits) / $clockFrequency) . "sec\n";

	echo "Seeking to data start (pos = " . $dataStart . ")\n";
	$dataContent = substr($oneFrame, $dataStart);

	if (substr($dataContent, 0, 3) == "ID3") {
		echo "** ID3 header detected\n";
	}

	fclose($handle);

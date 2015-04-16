<?php
    /*
     * (c) 2015 Denis Mysenko
     */

	$packetSize = 188;
	// 90Khz for MPEG TS
	$clockFrequency = 90000;
    $debug = false;

    define("FRAME_TYPE_INCORRECT", 0);
    define("FRAME_TYPE_PAT", 1);
    define("FRAME_TYPE_PES" , 2);
    define("FRAME_TYPE_PMT", 3);
    define("FRAME_TYPE_UNKNOWN", 99);

    $descriptorTags = array(
        15 => 'Audio',
        21 => 'MetaData in PES',
        27 => 'AVC video',
        37 => 'Metadata pointer',
        38 => 'Metadata'
    );

	function parseFrame($oneFrame) {
        global $debug, $clockFrequency, $descriptorTags;

        $sync_byte = $oneFrame[0];
        if ($debug) echo "==== TRANSPORT STREAM HEADER ====\n";
        if ($sync_byte == 'G')  { if ($debug) echo "8 bits: Sync byte: OK\n"; } else { return FRAME_TYPE_INCORRECT; }
        $frameType = FRAME_TYPE_UNKNOWN;

        $byte = decbin(ord($oneFrame[1]));
        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
        $byte = decbin(ord($oneFrame[2]));
        $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
        if ($debug) echo "1 bit: Transport Error Indicator = " . $bits[0] . "\n";
        $payloadStartIndicator = $bits[1];
        if ($debug) echo "1 bit: Payload Unit Start Indicator = " . $payloadStartIndicator . "\n";
        if ($debug) echo "1 bit: Transport Priority = " . $bits[2] . "\n";
        if ($debug) echo "13 bits: PID (dec: " . bindec(substr($bits, 3, 13)) . " hex: 0x" .
            dechex(bindec(substr($bits, 3, 13))) . ")\n";

        if (bindec(substr($bits, 3, 13)) == 0) {
            // PAT frame detected
            $frameType = FRAME_TYPE_PAT;
        }

        $byte = decbin(ord($oneFrame[3]));
        $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
        if ($debug) echo "2 bits: Scrambling control = " . $bits[0] . $bits[1] . "\n";
        if ($debug) echo "1 bit: Adaptation field exists = " . $bits[2] . "\n";
        if ($debug) echo "1 bit: Payload exists = " . $bits[3] . "\n";
        if ($debug) echo "4 bits: Continuity counter = " . substr($bits, 4, 4) . " (dec: " . bindec(substr($bits, 4, 4)) . ")\n";

        $pes_start = substr($oneFrame, 4, 3);
        if (ord($pes_start[0]) == 0 && ord($pes_start[1]) == 0 && ord($pes_start[2]) == 1 && $frameType == FRAME_TYPE_UNKNOWN) {
            // PES frame detected
            $frameType = FRAME_TYPE_PES;
        }

        if ($frameType == FRAME_TYPE_PAT) {
            if ($debug) echo "==== PAT TABLE ====\n";

            if ($payloadStartIndicator != 1 || ord($oneFrame[4]) != 0) {
                // At this moment PAT frames without empty pointer field are not supported
                return FRAME_TYPE_UNKNOWN;
            }

            $table_id = $oneFrame[5];
            if ($debug) echo "8 bits: Table ID = " . ord($table_id) . " (dec)\n";

            $byte = decbin(ord($oneFrame[6]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[7]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "1 bit: Section index indicator = " . $bits[0] . "\n";
            if ($debug) echo "1 bit: Private bit = " . $bits[1] . "\n";
            if ($debug) echo "2 bits: Reserved bits = " . $bits[2] . $bits[3] . "\n";
            if ($debug) echo "2 bits: Section length unused = " . $bits[4] . $bits[5] . "\n";
            // Section length will be used to build a correct loop below
            $section_length = substr($bits, 6);
            if ($debug) echo "10 bits: Section length = " . bindec($section_length) . "\n";

            $byte = decbin(ord($oneFrame[8]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[9]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "16 bits: Table ID extension = " . bindec($bits) . "\n";

            $byte = decbin(ord($oneFrame[10]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "2 bits: Reserved bits = " . $bits[0] . $bits[1] . "\n";
            if ($debug) echo "5 bits: Version number = " . bindec(substr($bits, 2, 5)) . "\n";
            if ($debug) echo "1 bits: Current/next indicator = " . $bits[7] . "\n";

            if ($debug) echo "8 bits: Section number = " . ord($oneFrame[11]) . "\n";
            if ($debug) echo "8 bits: Last section number = " . ord($oneFrame[12]) . "\n";

            // Todo: make a loop to support several programs in the table
            $byte = decbin(ord($oneFrame[13]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[14]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "16 bits: Program number = " . bindec($bits) . "\n";

            $byte = decbin(ord($oneFrame[15]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[16]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);

            if ($debug) echo "3 bits: Reserved bits = " . $bits[0] . $bits[1] . $bits[2] . "\n";
            if ($debug) echo "13 bits: Network/program PID " . bindec(substr($bits, 3)) . " (dec) / 0x" .
                dechex(bindec(substr($bits, 3))) . " (hex)\n";

            // Todo: verify checksum
            $crc32 = substr($oneFrame, 17, 4);
        } else if ($frameType == FRAME_TYPE_PES) {
            if ($debug) echo "==== PES HEADER ====\n";
            if ($debug) echo "24 bits: Packet start prefix: OK\n";

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
                // No optional PES header detected, aborting
                return true;
            }
            if ($debug) echo "2 bits: Scrambling control = " . $bits[2] . $bits[3] . "\n";
            if ($debug) echo "1 bit: Priority = " . $bits[4] . "\n";
            if ($debug) echo "1 bit: Data alignment indicator = " . $bits[5] . "\n";
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
            if ($debug) echo "1 bit: Extension flag = " . $bits[7] . "\n";

            if ($debug) echo "8 bits: PES header length = " . ord($oneFrame[12]) . " (bytes)\n";
            $dataStart = 14 + ord($oneFrame[12]) - 1;
            if ($debug) echo "** Data will start at " . $dataStart . " bytes\n";

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

            if ($debug && $bits[0] . $bits[1] . $bits[2] . $bits[3] == '0010')
                echo "4 bits: PTS padding: OK\n";

            // Extract PTS from the bit pattern
            $pts_bits = substr($bits, 4, 3) . substr($bits, 8, 15) . substr($bits, 24, 15);
            if ($debug) echo "PTS = " . bindec($pts_bits) . " (dec) or " . (bindec($pts_bits) / $clockFrequency) . "sec\n";

            if ($debug) echo "Seeking to data start (pos = " . $dataStart . ")\n";
            $dataContent = substr($oneFrame, $dataStart);

            if (substr($dataContent, 0, 3) == "ID3") {
                echo "** ID3 metadata detected at PTS " . (bindec($pts_bits) / $clockFrequency) . "sec(s) \n";
            }
        } else {
            if ($payloadStartIndicator != 1 || ord($oneFrame[4]) != 0) {
                // At this moment PAT frames without empty pointer field are not supported
                return FRAME_TYPE_UNKNOWN;
            }

            $table_id = ord($oneFrame[5]);
            if ($debug) echo "8 bits: Table ID = " . $table_id . " (dec)\n";

            $byte = decbin(ord($oneFrame[6]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[7]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "1 bit: Section index indicator = " . $bits[0] . "\n";
            if ($debug) echo "1 bit: Private bit = " . $bits[1] . "\n";
            if ($debug) echo "2 bits: Reserved bits = " . $bits[2] . $bits[3] . "\n";

            // Looks like a PMT table (we know it's not a PAT table already)
            if ($table_id == 2 && $bits[1] == '0' && $bits[2] . $bits[3] == '11') {
                $frameType = FRAME_TYPE_PMT;
            }

            if ($debug) echo "2 bits: Section length unused = " . $bits[4] . $bits[5] . "\n";
            // Section length will be used to build a correct loop below
            $section_length = bindec(substr($bits, 6));
            if ($debug) echo "10 bits: Section length = " . $section_length . "\n";

            $byte = decbin(ord($oneFrame[8]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[9]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "16 bits: Table ID extension = " . bindec($bits) . "\n";

            $byte = decbin(ord($oneFrame[10]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "2 bits: Reserved bits = " . $bits[0] . $bits[1] . "\n";
            if ($debug) echo "5 bits: Version number = " . bindec(substr($bits, 2, 5)) . "\n";
            if ($debug) echo "1 bits: Current/next indicator = " . $bits[7] . "\n";

            if ($debug) echo "8 bits: Section number = " . ord($oneFrame[11]) . "\n";
            if ($debug) echo "8 bits: Last section number = " . ord($oneFrame[12]) . "\n";

            // At this moment, we don't know how to parse other types of frames
            if ($frameType != FRAME_TYPE_PMT) return FRAME_TYPE_UNKNOWN;

            $byte = decbin(ord($oneFrame[13]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[14]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "3 bits: Reserved bits = " . $bits[0] . $bits[1] . $bits[2] . "\n";
            if ($debug) echo "13 bits: PCR PID = " . bindec(substr($bits, 3)) . " (dec) / 0x" .
                dechex(bindec(substr($bits, 3))) . " (hex)\n";

            $byte = decbin(ord($oneFrame[15]));
            $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
            $byte = decbin(ord($oneFrame[16]));
            $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
            if ($debug) echo "4 bits: Reserved bits = " . $bits[0] . $bits[1] . $bits[2] . $bits[3] . "\n";
            if ($debug) echo "2 bits: Program info unused bits = " . $bits[4] . $bits[5] . "\n";
            $programInfoLength = bindec(substr($bits, 6));
            if ($debug) echo "10 bits: Program info length = " . $programInfoLength . " bytes\n";

            if ($programInfoLength > 0) {
                if ($debug) echo "\n** Descriptor loop begin\n";
                for($i = 17; $i < 17 + $programInfoLength; ) {
                    $typeTitle = empty($descriptorTags[ord($oneFrame[$i])]) ? "N/A" : $descriptorTags[ord($oneFrame[$i])];
                    if ($debug) echo "8 bits: Descriptor tag = " . ord($oneFrame[$i]) . " (" . $typeTitle . ")\n";
                    if ($debug) echo "8 bits: Descriptor length = " . ord($oneFrame[$i + 1]) . "\n";

                    $i = $i + 2 + ord($oneFrame[$i + 1]);
                }
                if ($debug) echo "** Descriptor loop end\n";
            } else {
                $i = 17;
            }

            // Table size is full section length minus 4 bytes of CRC32 in the end and minus
            // 4 bytes of data above
            $table_end = $section_length + 7 - 4;

            if ($debug) echo "\n** Program loop begin\n";

            for (; $i <= $table_end; ) {
                $typeTitle = empty($descriptorTags[ord($oneFrame[$i])]) ? "N/A" : $descriptorTags[ord($oneFrame[$i])];
                if ($debug) echo "8 bits: Stream type = " . ord($oneFrame[$i]) . " (" . $typeTitle . ") \n";

                $byte = decbin(ord($oneFrame[$i + 1]));
                $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
                $byte = decbin(ord($oneFrame[$i + 2]));
                $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);
                if ($debug) echo "3 bits: Reserved bits = " . $bits[0] . $bits[1] . $bits[2] . "\n";
                if ($debug) echo "13 bits: Elementary PID = " . bindec(substr($bits, 3)) . " (dec) / 0x" .
                    dechex(bindec(substr($bits, 3))) . " (hex)\n";

                $byte = decbin(ord($oneFrame[$i + 3]));
                $bits = str_pad($byte, 8, 0, STR_PAD_LEFT);
                $byte = decbin(ord($oneFrame[$i + 4]));
                $bits = $bits . str_pad($byte, 8, 0, STR_PAD_LEFT);

                if ($debug) echo "4 bits: Reserved bits = " . $bits[0] . $bits[1] . $bits[2] . $bits[3] . "\n";
                if ($debug) echo "2 bits: ES Info length unused = " . $bits[4] . $bits[5] . "\n";
                if ($debug) echo "10 bits: ES Info length = " . bindec(substr($bits, 6)) . " bytes\n";

                /*
                if (bindec(substr($bits, 6)) > 0) {
                    for($ii = $i + 5; $ii < $i + 5 + bindec(substr($bits, 6)); ) {
                        $typeTitle = empty($descriptorTags[ord($oneFrame[$ii])]) ? "N/A" : $descriptorTags[ord($oneFrame[$ii])];
                        if ($debug) echo "\t8 bits: Descriptor tag = " . ord($oneFrame[$ii]) . " (" . $typeTitle . ")\n";
                        if ($debug) echo "\t8 bits: Descriptor length = " . ord($oneFrame[$ii + 1]) . "\n";

                        $ii = $ii + 2 + ord($oneFrame[$ii + 1]);
                    }
                } */

                $i = $i + 5 + bindec(substr($bits, 6));
                echo "\n";
            }
            if ($debug) echo "** Program loop end\n";

            // Todo: verify checksum
            $crc32 = substr($oneFrame, $table_end + 1, 4);
        }

        return $frameType;
	}

	if (empty($argv[1]) || !file_exists($argv[1])) {
		die("Syntax: " . $argv[0] . " <filename> [-d]\n");
	} else {
		$filename = $argv[1];
	}

    if (!empty($argv[2]) && $argv[2] == "-d") $debug = true;

	if (filesize($filename) % 188 != 0) {
		die("Broken MPEG TS stream – filesize must be a power of 188\n");
	}

	$handle = fopen($filename, "r");
	$filePosition = 0;
	$frameCounter = 0;

	while($filePosition < filesize($filename)) {
		fseek($handle, $filePosition);
		if ($debug) echo "Seeking to " . ftell($handle) . "";
		$oneFrame = fread($handle, $packetSize);
		if ($debug) echo ", read " . strlen($oneFrame) . " bytes\n";
		if (strlen($oneFrame) != $packetSize) die("Received frame of incorrect size != " . $packetSize . " (pos=" . $filePosition . ")\n");
		if (parseFrame($oneFrame)) { $frameCounter++; }
        if ($debug) echo "\n";
        $filePosition += $packetSize;
	}

    echo "Parsed " . $frameCounter . " MPEG TS frames\n";

	fclose($handle);

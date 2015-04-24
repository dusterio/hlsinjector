<?php
    /*
     * HLS Metadata Injector
     * (c) 2015 Denis Mysenko
     */

	$packetSize = 188;
	// 90Khz for MPEG TS
	$clockFrequency = 90000;
    $debug = false;
    // 10 megabytes – if the input file is smaller, load it into RAM fully
    $memoryLimit = 1024 * 1024 * 10;

    define("FRAME_TYPE_INCORRECT", 0);
    define("FRAME_TYPE_PAT", 1);
    define("FRAME_TYPE_PES" , 2);
    define("FRAME_TYPE_PMT", 3);
    define("FRAME_TYPE_UNKNOWN", 99);

    define("LAUNCH_MODE_ANALYZE", 0);
    define("LAUNCH_MODE_INJECT", 1);

    $descriptorTags = array(
        15 => 'Audio',
        21 => 'MetaData in PES',
        27 => 'AVC video',
        37 => 'Metadata pointer',
        38 => 'Metadata'
    );

    // This is received through reverse engineering of Apples mediafilesegmenter output
    $appleMetaDescriptor = array(
        'tag' => 37,
        'length' => 15,
        'content' => array(0xFF, 0xFF, 0x49, 0x44, 0x33, 0x20, 0xFF, 0x49, 0x44, 0x33, 0x20, 0x00, 0x1F, 0x00, 0x01)
    );

    // This is received the same way as above :)
    $appleMetaStream = array(
        'type' => 21,
        'ES_length' => 15,
        'ES_descriptor_tag' => 38,
        'ES_descriptor_length' => 13,
        'ES_descriptor_content' => array(0xFF, 0xFF, 0x49, 0x44, 0x33, 0x20, 0xFF, 0x49, 0x44, 0x33, 0x20, 0x00, 0x0F)
    );

    $crc32Table = array(
        0x00000000, 0x04c11db7, 0x09823b6e, 0x0d4326d9, 0x130476dc, 0x17c56b6b, 0x1a864db2, 0x1e475005,
        0x2608edb8, 0x22c9f00f, 0x2f8ad6d6, 0x2b4bcb61, 0x350c9b64, 0x31cd86d3, 0x3c8ea00a, 0x384fbdbd,
        0x4c11db70, 0x48d0c6c7, 0x4593e01e, 0x4152fda9, 0x5f15adac, 0x5bd4b01b, 0x569796c2, 0x52568b75,
        0x6a1936c8, 0x6ed82b7f, 0x639b0da6, 0x675a1011, 0x791d4014, 0x7ddc5da3, 0x709f7b7a, 0x745e66cd,
        0x9823b6e0, 0x9ce2ab57, 0x91a18d8e, 0x95609039, 0x8b27c03c, 0x8fe6dd8b, 0x82a5fb52, 0x8664e6e5,
        0xbe2b5b58, 0xbaea46ef, 0xb7a96036, 0xb3687d81, 0xad2f2d84, 0xa9ee3033, 0xa4ad16ea, 0xa06c0b5d,
        0xd4326d90, 0xd0f37027, 0xddb056fe, 0xd9714b49, 0xc7361b4c, 0xc3f706fb, 0xceb42022, 0xca753d95,
        0xf23a8028, 0xf6fb9d9f, 0xfbb8bb46, 0xff79a6f1, 0xe13ef6f4, 0xe5ffeb43, 0xe8bccd9a, 0xec7dd02d,
        0x34867077, 0x30476dc0, 0x3d044b19, 0x39c556ae, 0x278206ab, 0x23431b1c, 0x2e003dc5, 0x2ac12072,
        0x128e9dcf, 0x164f8078, 0x1b0ca6a1, 0x1fcdbb16, 0x018aeb13, 0x054bf6a4, 0x0808d07d, 0x0cc9cdca,
        0x7897ab07, 0x7c56b6b0, 0x71159069, 0x75d48dde, 0x6b93dddb, 0x6f52c06c, 0x6211e6b5, 0x66d0fb02,
        0x5e9f46bf, 0x5a5e5b08, 0x571d7dd1, 0x53dc6066, 0x4d9b3063, 0x495a2dd4, 0x44190b0d, 0x40d816ba,
        0xaca5c697, 0xa864db20, 0xa527fdf9, 0xa1e6e04e, 0xbfa1b04b, 0xbb60adfc, 0xb6238b25, 0xb2e29692,
        0x8aad2b2f, 0x8e6c3698, 0x832f1041, 0x87ee0df6, 0x99a95df3, 0x9d684044, 0x902b669d, 0x94ea7b2a,
        0xe0b41de7, 0xe4750050, 0xe9362689, 0xedf73b3e, 0xf3b06b3b, 0xf771768c, 0xfa325055, 0xfef34de2,
        0xc6bcf05f, 0xc27dede8, 0xcf3ecb31, 0xcbffd686, 0xd5b88683, 0xd1799b34, 0xdc3abded, 0xd8fba05a,
        0x690ce0ee, 0x6dcdfd59, 0x608edb80, 0x644fc637, 0x7a089632, 0x7ec98b85, 0x738aad5c, 0x774bb0eb,
        0x4f040d56, 0x4bc510e1, 0x46863638, 0x42472b8f, 0x5c007b8a, 0x58c1663d, 0x558240e4, 0x51435d53,
        0x251d3b9e, 0x21dc2629, 0x2c9f00f0, 0x285e1d47, 0x36194d42, 0x32d850f5, 0x3f9b762c, 0x3b5a6b9b,
        0x0315d626, 0x07d4cb91, 0x0a97ed48, 0x0e56f0ff, 0x1011a0fa, 0x14d0bd4d, 0x19939b94, 0x1d528623,
        0xf12f560e, 0xf5ee4bb9, 0xf8ad6d60, 0xfc6c70d7, 0xe22b20d2, 0xe6ea3d65, 0xeba91bbc, 0xef68060b,
        0xd727bbb6, 0xd3e6a601, 0xdea580d8, 0xda649d6f, 0xc423cd6a, 0xc0e2d0dd, 0xcda1f604, 0xc960ebb3,
        0xbd3e8d7e, 0xb9ff90c9, 0xb4bcb610, 0xb07daba7, 0xae3afba2, 0xaafbe615, 0xa7b8c0cc, 0xa379dd7b,
        0x9b3660c6, 0x9ff77d71, 0x92b45ba8, 0x9675461f, 0x8832161a, 0x8cf30bad, 0x81b02d74, 0x857130c3,
        0x5d8a9099, 0x594b8d2e, 0x5408abf7, 0x50c9b640, 0x4e8ee645, 0x4a4ffbf2, 0x470cdd2b, 0x43cdc09c,
        0x7b827d21, 0x7f436096, 0x7200464f, 0x76c15bf8, 0x68860bfd, 0x6c47164a, 0x61043093, 0x65c52d24,
        0x119b4be9, 0x155a565e, 0x18197087, 0x1cd86d30, 0x029f3d35, 0x065e2082, 0x0b1d065b, 0x0fdc1bec,
        0x3793a651, 0x3352bbe6, 0x3e119d3f, 0x3ad08088, 0x2497d08d, 0x2056cd3a, 0x2d15ebe3, 0x29d4f654,
        0xc5a92679, 0xc1683bce, 0xcc2b1d17, 0xc8ea00a0, 0xd6ad50a5, 0xd26c4d12, 0xdf2f6bcb, 0xdbee767c,
        0xe3a1cbc1, 0xe760d676, 0xea23f0af, 0xeee2ed18, 0xf0a5bd1d, 0xf464a0aa, 0xf9278673, 0xfde69bc4,
        0x89b8fd09, 0x8d79e0be, 0x803ac667, 0x84fbdbd0, 0x9abc8bd5, 0x9e7d9662, 0x933eb0bb, 0x97ffad0c,
        0xafb010b1, 0xab710d06, 0xa6322bdf, 0xa2f33668, 0xbcb4666d, 0xb8757bda, 0xb5365d03, 0xb1f740b4
    );

    // Calculate CRC32 byte by byte using a table
    // Ported from Java: http://stackoverflow.com/questions/19048392/check-crc32-in-transport-stream-pat-section-java
    function calculateCRC ($input) {
        global $crc32Table;

        $crc = 0xffffffff;

        for($i = 0; $i < strlen($input); $i++) {
            // java: crc = (crc << 8) ^ CRC32_TABLE[((crc >> 24) ^ (b & 0xff)) & 0xff];
            // C: crc = (crc << 8) ^ crc_table[((crc >> 24) ^ *data++) & 0xff];
            $crc = ($crc << 8) ^ $crc32Table[(($crc >> 24) ^ ord($input[$i])) & 0xFF];
        }

        return $crc & 0xffffffff;
    }

    // Convert string to a sequence of hex numbers
    function strToHex($string) {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $ord = ord($string[$i]);
            $hexCode = dechex($ord);
            $hex .= substr('0' . $hexCode, -2);
        }

        return strToUpper($hex);
    }

    // Parse one MPEG TS frame, return some important details about the frame as array
	function parseFrame($oneFrame) {
        global $debug, $clockFrequency, $descriptorTags;

        $response = array();
        $sync_byte = $oneFrame[0];

        if ($debug) echo "==== TRANSPORT STREAM HEADER ====\n";
        if ($sync_byte == 'G') {
            $response['frameType'] = FRAME_TYPE_UNKNOWN;
            if ($debug) echo "8 bits: Sync byte: OK\n";
        } else {
            $response['frameType'] = FRAME_TYPE_INCORRECT;
            $response['error'] = "Sync byte missing";
            return $response;
        }

        $twoBytes = (ord($oneFrame[1]) << 8) + ord($oneFrame[2]);

        if ($debug) echo "1 bit: Transport Error Indicator = " . (($twoBytes >> 15) & 1) . "\n";
        $payloadStartIndicator = ($twoBytes >> 14) & 1;
        if ($debug) echo "1 bit: Payload Unit Start Indicator = " . $payloadStartIndicator . "\n";
        if ($debug) echo "1 bit: Transport Priority = " . (($twoBytes >> 13) & 1) . "\n";

        $response['streamPID'] = ($twoBytes & 8191);
        if ($debug) echo "13 bits: PID (dec: " . $response['streamPID'] . " hex: 0x" . dechex($response['streamPID']) . ")\n";

        // PAT frame detected, its PID is always 0
        if ($response['streamPID'] == 0) {
            $response['frameType'] = FRAME_TYPE_PAT;
        }

        $oneByte = ord($oneFrame[3]);
        if ($debug) echo "2 bits: Scrambling control = " . (($oneByte >> 6) & 3) . "\n";
        if ($debug) echo "1 bit: Adaptation field exists = " . (($oneByte >> 5) & 1) . "\n";
        if ($debug) echo "1 bit: Payload exists = " . (($oneByte >> 4) & 1) . "\n";

        $response['cc'] = $oneByte & 15;
        if ($debug) echo "4 bits: Continuity counter = " . $response['cc'] . "\n";

        // PES frame detected
        if (ord($oneFrame[4]) == 0 && ord($oneFrame[5]) == 0 && ord($oneFrame[6]) == 1 && $response['frameType'] == FRAME_TYPE_UNKNOWN) {
            $response['frameType'] = FRAME_TYPE_PES;
        }

        if ($response['frameType'] == FRAME_TYPE_PAT) {
            if ($debug) echo "==== PAT TABLE ====\n";

            if ($payloadStartIndicator != 1 || ord($oneFrame[4]) != 0) {
                // At this moment PAT frames without empty pointer field are not supported
                $response['frameType'] = FRAME_TYPE_UNKNOWN;
                return $response;
            }

            $table_id = ord($oneFrame[5]);
            if ($debug) echo "8 bits: Table ID = " . $table_id . " (dec)\n";

            $twoBytes = (ord($oneFrame[6]) << 8) + ord($oneFrame[7]);
            if ($debug) echo "1 bit: Section index indicator = " . (($twoBytes >> 15) & 1) . "\n";
            if ($debug) echo "1 bit: Private bit = " . (($twoBytes >> 14) & 1) . "\n";
            if ($debug) echo "2 bits: Reserved bits = " . (($twoBytes >> 12) & 3) . "\n";
            if ($debug) echo "2 bits: Section length unused = " . (($twoBytes >> 10) & 3) . "\n";
            // Section length will be used to build a correct loop below
            $section_length = $twoBytes & 1023;
            if ($debug) echo "10 bits: Section length = " . $section_length . "\n";

            $twoBytes = (ord($oneFrame[8]) << 8) + ord($oneFrame[9]);
            if ($debug) echo "16 bits: Table ID extension = " . $twoBytes . "\n";

            $oneByte = ord($oneFrame[10]);
            if ($debug) echo "2 bits: Reserved bits = " . (($oneByte >> 6) & 3) . "\n";
            if ($debug) echo "5 bits: Version number = " . (($oneByte >> 1) & 31) . "\n";
            if ($debug) echo "1 bits: Current/next indicator = " . ($oneByte & 1) . "\n";

            if ($debug) echo "8 bits: Section number = " . ord($oneFrame[11]) . "\n";
            if ($debug) echo "8 bits: Last section number = " . ord($oneFrame[12]) . "\n";

            if ($debug) echo "** Program list loop start\n";
            $response['programs'] = array();
            for($i = 13; $i < 8 + $section_length - 4;) {
                $programId = (ord($oneFrame[$i]) << 8) + ord($oneFrame[$i + 1]);
                if ($debug) echo "16 bits: Program number = " . $programId . "\n";

                $twoBytes = (ord($oneFrame[$i + 2]) << 8) + ord($oneFrame[$i + 3]);
                if ($debug) echo "3 bits: Reserved bits = " . (($twoBytes >> 12) & 7) . "\n";
                $pid = ($twoBytes & 8191);
                if ($debug) echo "13 bits: Network/program PID " . $pid . " (dec) / 0x" .
                    dechex($pid) . " (hex)\n";

                $response['programs'][] = array(
                    'id' => $programId,
                    'PMTPID' => $pid
                );

                $i = $i + 4;
            }
            if ($debug) echo "** Program list loop end\n";

            // Computing checksum and verifying it against the one supplied in the stream
            $crc32 = substr($oneFrame, 17, 4);
            $tableContent = substr($oneFrame, 5, $section_length - 1 + 4);
            $crc = ($crc32[0] << 24) + ($crc32[1] << 16) + ($crc32[2] << 8) + $crc32[3];

            if ($debug) echo "** CRC checksum contained = 0x" . dechex($crc) . "\n";
            if (calculateCRC($tableContent) == 0) {
                if ($debug) echo "** CRC checksum check success\n";
            } else {
                if ($debug) echo "** CRC checksum check failed\n";
                $response['error'] = "CRC mismatch";
            }
        } else if ($response['frameType'] == FRAME_TYPE_PES) {
            if ($debug) echo "==== PES HEADER ====\n";
            if ($debug) echo "24 bits: Packet start prefix: OK\n";

            $stream_id = ord($oneFrame[7]);
            if ($debug) echo "8 bits: Stream ID = " . $stream_id . " (dec) / 0x" . dechex($stream_id) ." (hex)\n";

            if ($debug) echo "16 bits: PES packet length = " . (($oneFrame[8] << 8) + $oneFrame[9]) . " (bytes)\n";

            $oneByte = ord($oneFrame[10]);

            if($debug) echo "==== OPTIONAL PES HEADER ====\n";
            if ((($oneByte >> 7) & 1) == 1 && (($oneByte >> 6) & 1) == 0) {
                if ($debug) echo "2 bits: Optional header marker: OK\n";
            } else {
                // No optional PES header detected, aborting
                $response['frameType'] == FRAME_TYPE_UNKNOWN;
                return $response;
            }

            if ($debug) echo "2 bits: Scrambling control = " . (($oneByte >> 4) & 3) . "\n";
            if ($debug) echo "1 bit: Priority = " . (($oneByte >> 3) & 1) . "\n";
            if ($debug) echo "1 bit: Data alignment indicator = " . (($oneByte >> 2) & 1) . "\n";
            if ($debug) echo "1 bit: Copyright = " . (($oneByte >> 1) & 1) . "\n";
            if ($debug) echo "1 bit: Original or copy = " . ($oneByte & 1) . "\n";

            $oneByte = ord($oneFrame[11]);

            if ($debug) echo "2 bits: PTS & DTS indicator = " . (($oneByte >> 6) & 3) . "\n";
            if ((($oneByte >> 6) & 3) == 2) {
                if ($debug) echo "** Only PTS is expected\n";
                $ptsOnly = true;
            } else {
                // We can only parse PTS at the moment anyway
                $ptsOnly = false;
                return $response;
            }

            if ($debug) echo "1 bit: ESCR flag = " . (($oneByte >> 5) & 1) . "\n";
            if ($debug) echo "1 bit: ES rate flag = " . (($oneByte >> 4) & 1) . "\n";
            if ($debug) echo "1 bit: DSM trick mode flag = " . (($oneByte >> 3) & 1) . "\n";
            if ($debug) echo "1 bit: Copy info flag = " . (($oneByte >> 2) & 1) . "\n";
            if ($debug) echo "1 bit: CRC flag = " . (($oneByte >> 1) & 1) . "\n";
            if ($debug) echo "1 bit: Extension flag = " . ($oneByte & 1) . "\n";

            if ($debug) echo "8 bits: PES header length = " . ord($oneFrame[12]) . " (bytes)\n";
            $dataStart = 14 + ord($oneFrame[12]) - 1;
            if ($debug) echo "** Data will start at " . $dataStart . " bytes\n";

            // 0100 1100  0010 1100  0010 1100  0010 1100  0010 1100
            $oneByte = ord($oneFrame[13]);

            if ((($oneByte >> 4) & 15) == 2) {
                if ($debug) echo "4 bits: PTS padding: OK\n";
            } else {
                return $response;
            }

            // Use bitwise operators on 64 bit systems
            if (PHP_INT_SIZE == 8) {
                $fiveBytes = (ord($oneFrame[13]) << 32) + (ord($oneFrame[14]) << 24) + (ord($oneFrame[15]) << 16) + (ord($oneFrame[16]) << 8) + ord($oneFrame[17]);
                $pts = 0;
                $pts += ($fiveBytes >> 3) & (0x0007 << 30);
                $pts += ($fiveBytes >> 2) & (0x7fff << 15);
                $pts += ($fiveBytes >> 1) & (0x7fff <<  0);
            } else {
                // Extract PTS from the bit pattern
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

                $pts_bits = substr($bits, 4, 3) . substr($bits, 8, 15) . substr($bits, 24, 15);
                $pts = bindec($pts_bits);
            }

            if ($debug) echo "PTS = " . $pts . " (dec) or " . ($pts / $clockFrequency) . "sec\n";

            if ($ptsOnly) {
                $response['timestamp'] = $pts;
                $response['raw_timestamp'] = $oneFrame[13] . $oneFrame[14] . $oneFrame[15] . $oneFrame[16] . $oneFrame[17];
            }

            if ($debug) {
                echo "Seeking to data start (pos = " . $dataStart . ")\n";
                $dataContent = substr($oneFrame, $dataStart);
                echo "Meta content: " . preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9]/', ' ', $dataContent)) . "\n";
                if (substr($dataContent, 0, 3) == "ID3") {
                    echo "** ID3 metadata detected at PTS " . ($pts / $clockFrequency) . "sec(s) \n";
                }
            }
        } else {
            if ($payloadStartIndicator != 1 || ord($oneFrame[4]) != 0) {
                // At this moment PAT frames without empty pointer field are not supported
                $response['frameType'] = FRAME_TYPE_UNKNOWN;
                return $response;
            }

            $table_id = ord($oneFrame[5]);
            if ($debug) echo "8 bits: Table ID = " . $table_id . " (dec)\n";

            $twoBytes = (ord($oneFrame[6]) << 8) + ord($oneFrame[7]);

            if ($debug) echo "1 bit: Section index indicator = " . (($twoBytes >> 15) & 1) . "\n";
            if ($debug) echo "1 bit: Private bit = " . (($twoBytes >> 14) & 1) . "\n";
            if ($debug) echo "2 bits: Reserved bits = " . (($twoBytes >> 12) & 3) . "\n";

            // Looks like a PMT table (we know it's not a PAT table already)
            if ($table_id == 2 && (($twoBytes >> 14) & 1) == 0 && (($twoBytes >> 12) & 3) == 3) {
                $response['frameType'] = FRAME_TYPE_PMT;
            }

            if ($debug) echo "2 bits: Section length unused = " . (($twoBytes >> 10) & 3) . "\n";
            // Section length will be used to build a correct loop below
            $section_length = $twoBytes & 1023;
            if ($debug) echo "10 bits: Section length = " . $section_length . "\n";

            $table_id = (ord($oneFrame[8]) << 8) + ord($oneFrame[9]);
            if ($debug) echo "16 bits: Table ID extension = " . $table_id . "\n";

            $oneByte = ord($oneFrame[10]);
            if ($debug) echo "2 bits: Reserved bits = " . (($oneByte >> 6) & 3) . "\n";
            if ($debug) echo "5 bits: Version number = " . (($oneByte >> 1) & 31) . "\n";
            if ($debug) echo "1 bits: Current/next indicator = " . ($oneByte & 1) . "\n";

            if ($debug) echo "8 bits: Section number = " . ord($oneFrame[11]) . "\n";
            if ($debug) echo "8 bits: Last section number = " . ord($oneFrame[12]) . "\n";

            // At this moment, we don't know how to parse other types of frames
            if ($response['frameType'] != FRAME_TYPE_PMT) {
                $response['frameType'] = FRAME_TYPE_UNKNOWN;
                return $response;
            }

            $twoBytes = (ord($oneFrame[13]) << 8) + ord($oneFrame[14]);
            if ($debug) echo "3 bits: Reserved bits = " . (($twoBytes >> 13) & 7) . "\n";
            $response['PCRPID'] = $twoBytes & 8191;
            if ($debug) echo "13 bits: PCR PID = " . $response['PCRPID'] . " (dec) / 0x" .
                dechex($response['PCRPID']) . " (hex)\n";

            $twoBytes = (ord($oneFrame[15]) << 8) + ord($oneFrame[16]);
            if ($debug) echo "4 bits: Reserved bits = " . (($twoBytes >> 12) & 15) . "\n";
            if ($debug) echo "2 bits: Program info unused bits = " . (($twoBytes >> 10) & 3) . "\n";
            $programInfoLength = $twoBytes & 1023;
            if ($debug) echo "10 bits: Program info length = " . $programInfoLength . " bytes\n";

            if ($programInfoLength > 0) {
                $response['descriptors'] = array();
                if ($debug) echo "\n** Descriptor loop begin\n";
                for($i = 17; $i < 17 + $programInfoLength; ) {
                    $typeTitle = empty($descriptorTags[ord($oneFrame[$i])]) ? "N/A" : $descriptorTags[ord($oneFrame[$i])];
                    if ($debug) echo "8 bits: Descriptor tag = " . ord($oneFrame[$i]) . " (" . $typeTitle . ")\n";
                    if ($debug) echo "8 bits: Descriptor length = " . ord($oneFrame[$i + 1]) . "\n";
                    if ($debug) echo "N bits: Descriptor content = " . strToHex(substr($oneFrame,  $i + 2, ord($oneFrame[$i + 1]))) . "\n";
                    $response['descriptors'][] = array(
                        'tag' => ord($oneFrame[$i]),
                        'root' => 0,
                        'content' => substr($oneFrame,  $i + 2, ord($oneFrame[$i + 1]))
                    );

                    $i = $i + 2 + ord($oneFrame[$i + 1]);
                }
                if ($debug) echo "** Descriptor loop end\n";
            } else {
                $i = 17;
            }

            // Table size is full section length minus 4 bytes of CRC32 in the end and minus
            // 4 bytes of data above
            $table_end = $section_length + 7 - 4;
            $response['tableSnapshot'] = substr($oneFrame, $i, $table_end - $i + 1);

            if ($debug) echo "\n** Program loop begin\n";

            $response['streams'] = array();
            for (; $i <= $table_end; ) {
                $typeTitle = empty($descriptorTags[ord($oneFrame[$i])]) ? "N/A" : $descriptorTags[ord($oneFrame[$i])];
                if ($debug) echo "8 bits: Stream type = " . ord($oneFrame[$i]) . " (" . $typeTitle . ") \n";

                $twoBytes = (ord($oneFrame[$i + 1]) << 8) + ord($oneFrame[$i + 2]);
                if ($debug) echo "3 bits: Reserved bits = " . (($twoBytes >> 13) & 7) . "\n";
                $streamId = $twoBytes & 8191;
                if ($debug) echo "13 bits: Elementary PID = " . $streamId . " (dec) / 0x" .
                    dechex($streamId) . " (hex)\n";

                $twoBytes = (ord($oneFrame[$i + 3]) << 8) + ord($oneFrame[$i + 4]);

                if ($debug) echo "4 bits: Reserved bits = " . (($twoBytes >> 12) & 15) . "\n";
                if ($debug) echo "2 bits: ES Info length unused = " . (($twoBytes >> 10) & 3) . "\n";
                $info_length = $twoBytes & 1023;
                if ($debug) echo "10 bits: ES Info length = " . $info_length . " bytes\n";

                if ($info_length > 0) {
                    for($ii = $i + 5; $ii < $i + 5 + $info_length; ) {
                        $typeTitle = empty($descriptorTags[ord($oneFrame[$ii])]) ? "N/A" : $descriptorTags[ord($oneFrame[$ii])];
                        if ($debug) echo "\t8 bits: Descriptor tag = " . ord($oneFrame[$ii]) . " (" . $typeTitle . ")\n";
                        if ($debug) echo "\t8 bits: Descriptor length = " . ord($oneFrame[$ii + 1]) . "\n";
                        if ($debug) echo "\tN bits: Descriptor content = " . strToHex(substr($oneFrame,  $ii + 2, ord($oneFrame[$ii + 1]))) . "\n";

                        $response['descriptors'][] = array(
                            'tag' => ord($oneFrame[$i]),
                            'root' => $streamId,
                            'content' => substr($oneFrame,  $i + 2, ord($oneFrame[$ii + 1]))
                        );

                        $ii = $ii + 2 + ord($oneFrame[$ii + 1]);
                    }
                }

                $response['streams'][] = array(
                    'id' => $streamId,
                    'type' => ord($oneFrame[$i])
                );

                $i = $i + 5 + $info_length;
                if ($debug) echo "\n";
            }
            if ($debug) echo "** Program loop end\n";

            // Computing checksum and verifying it against the one supplied in the stream
            $crc32 = substr($oneFrame, $table_end + 1, 4);
            $tableContent = substr($oneFrame, 5, $section_length - 1 + 4);
            $crc = ($crc32[0] << 24) + ($crc32[1] << 16) + ($crc32[2] << 8) + $crc32[3];

            if ($debug) echo "** CRC checksum contained = 0x" . dechex($crc) . "\n";
            if (calculateCRC($tableContent) == 0) {
                if ($debug) echo "** CRC checksum check success\n";
            } else {
                if ($debug) echo "** CRC checksum check failed\n";
                $response['error'] = "CRC mismatch";
            }
        }

        return $response;
	}

    function generateID3Frame($tagContent) {
        // At this moment maximum length is 128 because we don't want to deal with size encoding or segmentation
        if (strlen($tagContent) > 127) return false;

        // First bytes are ID3 tag + version (4) + minor version (0) + flags (all 0)
        $frame = "ID3" . chr(04) . chr(00) . chr(00);

        // 4 bytes containing ID3 size
        // Size: original size + 8 extra bytes + 1 padding in the end
        $frame .= chr(00) . chr(00) . chr(00) . chr(strlen($tagContent) + 12);

        $frame .= "TPE1";
        // Content length: real length + padding + encoding ID
        $frame .= chr(00) . chr(00) . chr(00) . chr(strlen($tagContent) + 2);
        // Two empty bytes + encoding of 3
        $frame .= chr(00) . chr(00) . chr(03);
        $frame .= $tagContent;
        $frame .= chr(00);

        return $frame;
    }

    function generateMetaFrame($metaTag, $metastreamID, $rawTimestamp, $cc) {
        global $packetSize;

        // Create a blank filled frame
        $metaFrame = "";
        for($i = 0; $i < $packetSize; $i++) {
            $metaFrame .= chr(0xff);
        }

        // Continuity counter cannot be higher than 15
        if ($cc > 15) {
            $cc = 0;
        }

        // Put the meta data in the end of the frame
        for($i = 0; $i < strlen($metaTag); $i++) {
            $metaFrame[$packetSize - strlen($metaTag) + $i] = $metaTag[$i];
        }

        $metaFrame[0] = 'G';

        $bits = '010';
        $bits = $bits . str_pad((decbin($metastreamID)), 13, 0, STR_PAD_LEFT);

        $metaFrame[1] = chr(bindec(substr($bits, 0, 8)));
        $metaFrame[2] = chr(bindec(substr($bits, 8, 8)));
        $metaFrame[3] = chr(bindec("0001" . str_pad(decbin($cc), 4, 0, STR_PAD_LEFT)));
        $metaFrame[4] = chr(0);
        $metaFrame[5] = chr(0);
        $metaFrame[6] = chr(1);
        $metaFrame[7] = chr(13);
        $bits = str_pad(decbin(178), 16, 0, STR_PAD_LEFT);
        $metaFrame[8] = chr(bindec(substr($bits, 0, 8)));
        $metaFrame[9] = chr(bindec(substr($bits, 8, 8)));
        $metaFrame[10] = chr(bindec("10000100"));
        $metaFrame[11] = chr(bindec("10000000"));
        $metaFrame[12] = chr($packetSize - strlen($metaTag) - 13); // PES header length
        $metaFrame[13] = $rawTimestamp[0];
        $metaFrame[14] = $rawTimestamp[1];
        $metaFrame[15] = $rawTimestamp[2];
        $metaFrame[16] = $rawTimestamp[3];
        $metaFrame[17] = $rawTimestamp[4];

        return $metaFrame;
    }

    function modifyFrameCC($originalFrame, $cc = 0) {
        if ($cc < 0 || $cc > 15) $cc = 0;

        $newCC = ord($originalFrame[3]) & 240;
        $newCC += $cc;

        $originalFrame[3] = chr($newCC);
        return $originalFrame;
    }

    function modifyFramePMT($originalFrame, $metastreamID = null) {
        global $debug, $appleMetaDescriptor, $appleMetaStream, $packetSize;

        // Create a blank filled frame
        $modifiedFrame = str_pad("", $packetSize, chr(0xff));
        $extraBytes = 0;

        // Old section length
        $twoBytes = (ord($originalFrame[6]) << 8) + ord($originalFrame[7]);
        $section_length = $twoBytes & 1023;
        if ($debug) echo "10 bits: Old section length = " . $section_length . "\n";

        // These bytes can be plain copied
        for($i = 0; $i < 16; $i++) {
            $modifiedFrame[$i] = $originalFrame[$i];
        }

        // Calculate old program info length
        $twoBytes = (ord($originalFrame[15]) << 8) + ord($originalFrame[16]);
        $programInfoLength = $twoBytes & 1023;
        $oldProgramInfoLength = $programInfoLength;
        if ($debug) echo "10 bits: Old program info length = " . $oldProgramInfoLength . " bytes\n";

        if ($oldProgramInfoLength > 0) {
            // Copy old descriptors without change
            for($ii = 17; $ii <= 17 + $oldProgramInfoLength; $ii++) {
                $modifiedFrame[$ii] = $originalFrame[$ii];
            }

            $pointer = 17 + $oldProgramInfoLength;
        } else {
            $pointer = 17;
        }

        // Add our descriptor
        $modifiedFrame[$pointer++] = chr($appleMetaDescriptor['tag']);
        $modifiedFrame[$pointer++] = chr($appleMetaDescriptor['length']);
        $extraBytes += 2;

        for ($ii = 0; $ii < count($appleMetaDescriptor['content']); $ii++) {
            $modifiedFrame[$pointer++] = chr($appleMetaDescriptor['content'][$ii]);
            $extraBytes++;
        }

        // Modify program info length
        $programInfoLength = $oldProgramInfoLength + $extraBytes;
        //2 + count($appleMetaDescriptor['content']);
        if ($debug) echo "New program info length = " . $programInfoLength . "\n";
        if ($extraBytes < 256) {
            $modifiedFrame[16] = chr($programInfoLength);
        }

        // Table size is full section length minus 4 bytes of CRC32 in the end and minus
        // 4 bytes of data above
        $table_end = $section_length + 7 - 4;

        if ($metastreamID == null) {
            $highestPID = 0;
            for ($i = 17 + $oldProgramInfoLength; $i <= $table_end; ) {
                $twoBytes = (ord($originalFrame[$i + 1]) << 8) + ord($originalFrame[$i + 2]);

                $streamId = $twoBytes & 8191;
                if ($debug) echo "13 bits: Elementary PID = " . $streamId . " (dec) / 0x" .
                    dechex($streamId) . " (hex)\n";

                // Find the highest (numerically) stream PID
                if ($streamId > $highestPID) { $highestPID = $streamId; }
                $twoBytes = (ord($originalFrame[$i + 3]) << 8) + ord($originalFrame[$i + 4]);

                $i = $i + 5 + ($twoBytes & 1023);
            }

            $metastreamID = $highestPID + 1;
            if ($debug) echo "** Stream ID " . $metastreamID . " will be used for metadata\n";
        }

        // Copy old table in full
        for ($i = 17 + $oldProgramInfoLength; $i <= $table_end; $i++) {
            $modifiedFrame[$pointer++] = $originalFrame[$i];
        }

        // Add one more stream to the table
        $modifiedFrame[$pointer++] = chr($appleMetaStream['type']);
        $newBits = '111' . str_pad(decbin($metastreamID), 13, 0, STR_PAD_LEFT);
        $modifiedFrame[$pointer++] = chr(bindec(substr($newBits, 0, 8)));
        $modifiedFrame[$pointer++] = chr(bindec(substr($newBits, 8, 8)));
        $newBits = '111100' . str_pad(decbin($appleMetaStream['ES_length']), 10, 0, STR_PAD_LEFT);
        $modifiedFrame[$pointer++] = chr(bindec(substr($newBits, 0, 8)));
        $modifiedFrame[$pointer++] = chr(bindec(substr($newBits, 8, 8)));
        $extraBytes += 5;

        // Descriptor
        $modifiedFrame[$pointer++] = chr($appleMetaStream['ES_descriptor_tag']);
        $modifiedFrame[$pointer++] = chr($appleMetaStream['ES_descriptor_length']);
        $extraBytes += 2;

        for($i = 0; $i < count($appleMetaStream['ES_descriptor_content']); $i++) {
            $modifiedFrame[$pointer++] = chr($appleMetaStream['ES_descriptor_content'][$i]);
            $extraBytes++;
        }

        // Modify section length
        $section_length = $section_length + $extraBytes;
        if ($extraBytes < 256) {
            $modifiedFrame['7'] = chr(ord($modifiedFrame['7']) + $extraBytes);
        }
        //$newBits = substr($sectionLengthBits, 0, 6) . $newBits;
        if ($debug) echo "10 bits: New section length = " . $section_length . "\n";
        //$modifiedFrame[6] = chr(bindec(substr($newBits, 0, 8)));
        //$modifiedFrame[7] = chr(bindec(substr($newBits, 8, 8)));

        // Computing checksum and verifying it against the one supplied in the stream

        $tableContent = substr($modifiedFrame, 5, $pointer - 5);
        $crc32 = calculateCRC($tableContent);
        if ($debug) echo "** CRC checksum = 0x" . dechex($crc32) . "\n";
        $bits = str_pad(decbin($crc32), 32, 0, STR_PAD_LEFT);
        $modifiedFrame[$pointer++] = chr(bindec(substr($bits, 0, 8)));
        $modifiedFrame[$pointer++] = chr(bindec(substr($bits, 8, 8)));
        $modifiedFrame[$pointer++] = chr(bindec(substr($bits, 16, 8)));
        $modifiedFrame[$pointer] = chr(bindec(substr($bits, 24, 8)));

        $response = array();
        $response['frame'] = $modifiedFrame;
        $response['metastreamID'] = $metastreamID;
        return $response;
    }

    $legend = "HLS Injector by Denis Mysenko\nSyntax: " . $argv[0] . " -i filename -m mode [-o filename] [-d]";
    $legend .= "\n\n\t\t-i filename\tinput file (MPEG TS format)\n";
    $legend .= "\t\t-m mode\t\tchoose 'analyze' or 'inject'\n";
    $legend .= "\t\t-o filename\toutput filename in case of 'inject' mode\n";
    $legend .= "\t\t-e filename\twith with timed metadata (see README for format)\n";
    $legend .= "\t\t--metastart N\tstart CC for metadata stream from this number\n";
    $legend .= "\t\t-d\t\tenable debug mode\n\n";

    $shortParameters  = "";
    $shortParameters .= "i:";  // Required value – input filename
    $shortParameters .= "m:"; // Required value - mode of work
    $shortParameters .= "o:"; // Optional value - output filename
    $shortParameters .= "e:"; // Optional value – file with meta data
    $shortParameters .= "d"; // Optional key – debug mode

    $longParameters  = array(
        "metastart:"     // Required value – start CC for meta PID from this value
    );

    $commandLineOptions = getopt($shortParameters, $longParameters);

	if (empty($commandLineOptions['i']) || !file_exists($commandLineOptions['i']) || empty($commandLineOptions['m'])) {
		die($legend);
	} else {
		$filename = $commandLineOptions['i'];
	}

    switch($commandLineOptions['m']) {
        case "analyze":
            $launchMode = LAUNCH_MODE_ANALYZE;
            break;
        case "inject":
            $launchMode = LAUNCH_MODE_INJECT;
            break;
        default:
            die("Unknown mode: " . $commandLineOptions['m'] . "\n");
    }

    if ($launchMode == LAUNCH_MODE_INJECT && empty($commandLineOptions['o'])) {
        die("Please specify output file\n");
    }

    if ($launchMode == LAUNCH_MODE_INJECT && empty($commandLineOptions['e'])) {
        die("Please specify metadata file\n");
    }

    if ($launchMode == LAUNCH_MODE_INJECT) {
        $outputFile = $commandLineOptions['o'];
        if (file_exists($outputFile)) unlink($outputFile);
        $outputHandle = fopen($outputFile, "a");
        $outputContent = "";

        if (!file_exists($commandLineOptions['e'])) die("Cannot open metadata file\n");

        $metaHandle = fopen($commandLineOptions['e'], "r");
        $metaData = array();

        if ($metaHandle) {
            while(($oneLine = fgets($metaHandle)) != false) {
                $oneLine = trim($oneLine);
                if (strlen($oneLine) == 0) continue;

                preg_match('/^(\d+) ([a-zA-Z0-9]+) (.*)$/', $oneLine, $matches);

                if (!isset($matches[1]) || empty($matches[2]) || empty($matches[3]))
                    break;

                if (strtolower($matches[2]) != "id3" && strtolower($matches[2]) != "plaintext") break;
                if (intval($matches[1]) < 0) break;

                switch (strtolower($matches[2])) {
                    case "id3":
                        if (!file_exists($matches[3])) break;

                        $oneTag = file_get_contents($matches[3]);

                        break;

                    case "plaintext":
                        if(strlen($matches[3]) > 128) break;
                        $oneTag = generateID3Frame($matches[3]);

                        break;
                }

                // We want to fit ID3 tag in single transport frame
                if (strlen($oneTag) > 170) die("Maximum length of ID3 file is 170 bytes\n");

                $metaData[] = array(
                    'moment' => intval($matches[1]),
                    'tag' => $oneTag
                );
            }

            fclose($metaHandle);

            if (count($metaData) == 0) { die("Empty metadata file or wrong format\n"); } else {
                echo "** Imported " . count($metaData) . " metadata tags\n";
            }

            // Sort meta data chronologically
            function cmp($a, $b)
            {
                if ($a['moment'] == $b['moment']) {
                    return 0;
                }
                return ($a['moment'] < $b['moment']) ? -1 : 1;
            }

            usort($metaData, "cmp");
        } else {
            die("Cannot open metadata file\n");
        }
    }

    if (array_key_exists('d', $commandLineOptions)) $debug = true;

    $inputFileSize = filesize($filename);

    // Correct MPEG TS stream is comprised of equal, 188 byte packets
	if ($inputFileSize % $packetSize != 0) {
		die("Broken MPEG TS stream – filesize must be a power of " . $packetSize . "\n");
	} else {
        // Read the whole file into memory

        if ($inputFileSize > $memoryLimit) {
            $inputContent = false;
            $inputHandle = fopen($filename, "r");
        } else {
            $inputContent = file_get_contents($filename);
            $inputHandle = false;
        }
    }

	$filePosition = 0;
	$frameCounter = 0;
    $errorCounter = 0;

    if(!empty($commandLineOptions['metastart']) && is_int(intval($commandLineOptions['metastart'])) &&
        intval($commandLineOptions['metastart']) >= 0 && intval($commandLineOptions['metastart']) <= 15) {
        $metaCC = intval($commandLineOptions['metastart']);
    } else {
        $metaCC = 0;
    }

    $insertedCounter = 0;
    $initialShift = false;
    $ccCounters = array();
    $metastreamID = null;
    $currentStream = array();
    $currentStream['programs'] = array();
    $startTime = microtime(true);

	while($filePosition < $inputFileSize) {
        if ($inputHandle) {
            $oneFrame = fread($inputHandle, $packetSize);
        } else {
            $oneFrame = substr($inputContent, $filePosition, $packetSize);
        }

        /*$parsedResult = array(); $parsedResult['frameType'] = FRAME_TYPE_UNKNOWN;
        $parsedResult['cc'] = 0; $parsedResult['streamPID'] = 5; */
		$parsedResult = parseFrame($oneFrame);

        if (!empty($parsedResult['error'])) {
            echo "Error at frame " . $frameCounter . ": " . $parsedResult['error'] . "\n";
            $errorCounter++;
        }

        if ($parsedResult['frameType'] != FRAME_TYPE_INCORRECT) {
            $frameCounter++;

            // Check continuity counter
            if(!isset($ccCounters[$parsedResult['streamPID']])) {
                $ccCounters[$parsedResult['streamPID']] = -1;
            }

            $ccCounters[$parsedResult['streamPID']]++;
            if ($ccCounters[$parsedResult['streamPID']] == 16) { $ccCounters[$parsedResult['streamPID']] = 0; }

            if ($parsedResult['cc'] != $ccCounters[$parsedResult['streamPID']]) {
                if ($debug) echo "** CC counter mismatch " . $parsedResult['cc'] . " != " .
                    $ccCounters[$parsedResult['streamPID']] . "\n";
            }

            if ($parsedResult['frameType'] == FRAME_TYPE_PAT) {
                if ($debug) echo "PAT detected at frame " . $frameCounter . "\n";

                if (!empty($parsedResult['programs'])) {
                    foreach ($parsedResult['programs'] as $oneProgram) {
                        // Update our local stream table if this data is missing
                        if (empty($currentStream['programs'][$oneProgram['id']]['PMT_PID'])) {
                            if (empty($currentStream['programs'][$oneProgram['id']]))
                                $currentStream['programs'][$oneProgram['id']] = array();

                            $currentStream['programs'][$oneProgram['id']]['PMT_PID'] = $oneProgram['PMTPID'];
                        }
                    }
                }
            }

            if ($parsedResult['frameType'] == FRAME_TYPE_PMT) {
                if ($debug) echo "PMT detected at frame " . $frameCounter . "\n";

                $thisProgram = null;
                foreach($currentStream['programs'] as $key => $oneProgram) {
                    if ($oneProgram['PMT_PID'] == $parsedResult['streamPID']) {
                        $thisProgram = $key;
                        break;
                    }
                }

                if ($thisProgram == null) {
                    if ($debug) echo "** PMT belonging to unknown channel encountered at " . $frameCounter . "\n";
                } else {
                    if (empty($currentStream['programs'][$thisProgram]['PMT_snapshot'])) {
                        $currentStream['programs'][$thisProgram]['PMT_snapshot'] = $parsedResult['tableSnapshot'];
                    } else {
                        if ($currentStream['programs'][$thisProgram]['PMT_snapshot'] != $parsedResult['tableSnapshot']) {
                            die("Your PMT is changing over time, this feature is not supported yet\n");
                        }
                    }

                    if (!empty($parsedResult['streams'])) {
                        foreach($parsedResult['streams'] as $oneStream) {
                            if (empty($currentStream['programs'][$thisProgram]['streams']))
                                $currentStream['programs'][$thisProgram]['streams'] = array();

                            if (empty($currentStream['programs'][$thisProgram]['streams'][$oneStream['id']]))
                                $currentStream['programs'][$thisProgram]['streams'][$oneStream['id']] = $oneStream['type'];
                        }
                    }
                }
            }

            if ($launchMode == LAUNCH_MODE_INJECT) {
                // Modify PMT table – add our ID3 stream in the list
                if ($parsedResult['frameType'] == FRAME_TYPE_PMT) {
                    $response = modifyFramePMT($oneFrame, $metastreamID);
                    $oneFrame = $response['frame'];
                    if ($metastreamID == null) $metastreamID = $response['metastreamID'];
                }

                // We have a frame with PTS (presentation timestamp)
                // Therefore, we can decide whether we want to add an ID3 frame next
                if (!empty($parsedResult['timestamp'])) {
                    if (!$initialShift) $initialShift = ($parsedResult['timestamp'] / $clockFrequency);
                    if ($debug) echo "** Timestamp received: " . $parsedResult['timestamp'] . "\n";
                    if (count($metaData) > 0) {
                        if ($metastreamID && (($parsedResult['timestamp'] / $clockFrequency) - $initialShift) >= $metaData[0]['moment']) {
                            $metaFrame = generateMetaFrame($metaData[0]['tag'], $metastreamID, $parsedResult['raw_timestamp'], $metaCC);
                            echo "Inserting ID3 frame after frame " . $frameCounter . " (len=" . strlen($metaFrame) . ")\n";

                            array_shift($metaData);
                            if (strlen($metaFrame) != $packetSize) continue;

                            if ($inputHandle) {
                                fwrite($outputHandle, $metaFrame, $packetSize);
                            } else {
                                $outputContent .= $metaFrame;
                            }

                            $insertedCounter++;
                            $metaCC++;
                            if ($metaCC == 16) { $metaCC = 0; };
                        }
                    }
                }

                if ($inputHandle) {
                    fwrite($outputHandle, $oneFrame, $packetSize);
                } else {
                    $outputContent .= $oneFrame;
                }
            }
        } else {
            $errorCounter++;
        }

        if ($debug) echo "\n";
        $filePosition += $packetSize;
	}

    $streamCounter = 0;
    foreach($currentStream['programs'] as $oneProgram) {
        if (!empty($oneProgram['streams'])) {
            foreach($oneProgram['streams'] as $oneStream) $streamCounter++;
        }
    }

    echo "Parsed " . $frameCounter . " MPEG TS frames with " . $errorCounter . " errors\n";
    echo "Total of " . count($currentStream['programs']) . " programs and " . $streamCounter . " streams\n";

    if ($launchMode == LAUNCH_MODE_INJECT) {
        if ($inputHandle) {
            fclose($outputHandle);
        } else {
            fwrite($outputHandle, $outputContent);
            fclose($outputHandle);
        }
        echo "Injected " . $insertedCounter . " frames\n";
    }

	if ($inputHandle) fclose($inputHandle);
    echo "Finished in " . round(((microtime(true) - $startTime) * 1000), 3) . "ms\n";

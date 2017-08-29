<?php
        // $file = "http://dash.akamaized.net/dash264/TestCasesHD/2b/qualcomm/1/MultiResMPEG2.mpd";
        $file = $_POST["urlToLoad"];
        
        // echo $file;
        // load mpd file and remove new lines
        function loadFile($file) {
            $mpd = htmlspecialchars(file_get_contents($file));
            $newline = "~\n~";
            $replace = "";
            $mpdRaw = preg_replace($newline, $replace, $mpd);
            ////echo $mpdRaw ."<br><br>";
            return $mpdRaw;
        }
        $mpdRaw = loadFile($file);

        // extract MPD info
        function extractMPD($mpdRaw) {
            $patternMPD = "~&lt;MPD(.*?)&gt;~";
            $mpdText = "";
            preg_match($patternMPD, $mpdRaw, $mpdText);
            ////echo "mpdInfo";
            //print_r($mpdText[1]);
            ////echo "<br><br>";
            return $mpdText[1];
        }
        $mpdText = extractMPD($mpdRaw); // string
        
        // extract Period info
        function extractPeriod($mpdRaw, $offset=0) {
            $patternPeriod = "~&lt;Period(.*?)&lt;/Period&gt;~";
            $period = "";
            preg_match($patternPeriod, $mpdRaw, $period, PREG_OFFSET_CAPTURE, $offset);
            //print_r($period[1]);
            ////echo "<br><br>";
            
            $patternPer = "~&lt;Period(.*?)&gt;~";
            $periodInfo = "";
            preg_match($patternPer, $mpdRaw, $periodInfo, $offset);
            ////echo "periodInfo";
            //print_r($periodInfo[1]);
            //echo "<br><br>";
            return array($period[1][0], $period[1][1], $periodInfo[1]);
        }
        
        // extract AdaptationSet info
        function extractAdSet($periodText, $offset=0) {
            $patternAdSet = "~&lt;AdaptationSet(.*?)&lt;/AdaptationSet&gt;~";
            $adaptation = "";
            preg_match($patternAdSet, $periodText, $adaptation, PREG_OFFSET_CAPTURE, $offset);
            //print_r($adaptation[1]);
            //echo "<br><br>";
            
            $patternAd = "~&lt;AdaptationSet(.*?)&gt;~";
            $adaptationInfo = "";
            preg_match($patternAd, $periodText, $adaptationInfo, $offset);
            //echo "adaptationInfo";
            //print_r($adaptationInfo[1]);
            //echo "<br><br>";
            return array($adaptation[1][0], $adaptation[1][1], $adaptationInfo[1]);
        }
                
        // extract Representation info
        function extractRep($adaptationText, $offset=0) {
            $patternRep = "~&lt;Representation(.*?)&lt;/Representation&gt;~";
            $representation = "";
            preg_match($patternRep, $adaptationText, $representation, PREG_OFFSET_CAPTURE, $offset);
            //echo "representation";
            //print_r($representation[1]);
            //echo "<br><br>";
            return $representation[1];
        }
                       
        // find pattern AttributeName="AttributeValue"
        function extractPattern($text) {
            $pattern = "~(\w+)=&quot;(.*?)&quot;~";
            $matches = array();
            preg_match_all($pattern, $text, $matches, PREG_PATTERN_ORDER); 
            //print_r($matches[1]);
            //echo "<br><br>";
            //print_r($matches[2]);
            //echo "<br><br>";
            return $matches;
        }
        // $matches = extractPattern($mpdRaw); // 0-88 arrays
        
        // find desired attribute value
        function retrieveValue($key, $matches) {
            $index = array_search($key, $matches[1]);
            if ($index !== FALSE) {
                $value = $matches[2][$index];
                return $value;
            }
            else {
                return -1;
            }
        }
        
        function retrieveValue2($key, $matches) {
            if (retrieveValue($key, $matches) !== -1) {
                $idx1 = array_search($key, $matches[1]);
                $subKey = array_slice($matches[1], $idx1 + 1);
                $subValue = array_slice($matches[2], $idx1 + 1);
                $idx2 = array_search($key, $subKey);
                if ($idx2 !== FALSE) {
                    $value = $subValue[$idx2];
                    return $value;
                }
                else {
                    return retrieveValue($key, $matches);
                }
            }
            else {
                return -1;
            } 
        } 
        
        // create video master playlist
        function createVideoMaster($matches) {
            $videoMasterInfo = '#EXT-X-STREAM-INF:';
            if (in_array("bandwidth", $matches[1])) {$videoMasterInfo.='BANDWIDTH='.retrieveValue("bandwidth", $matches);}
            if (in_array("codecs", $matches[1])) {$videoMasterInfo.=',CODECS="'.retrieveValue("codecs", $matches).'"';}
            if (in_array("width", $matches[1]) && in_array("height", $matches[1])) {$videoMasterInfo.=',RESOLUTION='.retrieveValue("width", $matches).'*'.retrieveValue("height", $matches);}
            if (in_array("frameRate", $matches[1])) {$videoMasterInfo.=',FRAMERATE='.retrieveValue("frameRate", $matches);}
            $media = retrieveValue("media", $matches);
            $mediaURI = substr($media, 0, strrpos($media, "_")).'.m3u8';  // name for media playlist!!
            //echo $videoMasterInfo."<br>";
            //echo $mediaURI."<br>";  
            return array($mediaURI, $videoMasterInfo);
        }
        
        function createAudioMaster($matches) {
            $audioMasterInfo = '#EXT-X-MEDIA:TYPE=AUDIO';
            if (in_array("id", $matches[1])) {$audioMasterInfo.=',GROUP-ID="audio'.retrieveValue("id", $matches).'"';}
            $videoMasterInfo = ',AUDIO="audio'.retrieveValue("id", $matches).'"'."\n";
            if (in_array("lang", $matches[1])) {$audioMasterInfo.=',LANGUAGE="'.retrieveValue("lang", $matches).'"';}
            $media = retrieveValue("media", $matches);
            $mediaURI = substr($media, 0, strrpos($media, "_")).'.m3u8';  // name for media playlist!!
            $audioMasterInfo.=',URI="'.$mediaURI.'"'."\n";
            //echo $audioMasterInfo."<br>";
            return array($mediaURI, $videoMasterInfo, $audioMasterInfo);	
        }
                    
        // hierarchical loop
        $masterFile = fopen("master.m3u8", "w") or die("Unable to open file!");
        $videoMasterInfo = array();
        $videoURI = array();
        
        // period
        $periodIdx = 0;
        $period = extractPeriod($mpdRaw, $periodIdx);
        $periodText = $period[0];
        $periodIdx = $period[1];
        $periodInfo = $period[2];
        while ($periodIdx != FALSE) {
            // adaptation set
            $adaptationIdx = 0;
            $adaptationSet = extractAdSet($periodText, $adaptationIdx);
            $adaptationText = $adaptationSet[0];
            $adaptationIdx = $adaptationSet[1];
            $adaptationInfo = $adaptationSet[2];
            while ($adaptationIdx != FALSE) {
                // representation
                $representationIdx = 0;
                $representation = extractRep($adaptationText, $representationIdx);
                $representationText = $representation[0];
                $representationIdx = $representation[1];
                while ($representationIdx != FALSE) {
                    // concatenate all info for one representation
                    $allInfoPre = $mpdText.$periodInfo.$adaptationInfo.$representationText;
                    //echo "allInfo".$allInfoPre."<br><br>";
                    // all information in array(key, value)
                    $matches = extractPattern($allInfoPre);
                    // which type of multimedia
                    $mediaType = retrieveValue("mimeType", $matches);
                    // create video master playlist
                    if (strpos($mediaType, "video") !== FALSE) {
                        $videoMaster = createVideoMaster($matches);
                        array_push($videoMasterInfo, $videoMaster[1]);
                        $mediaURI = $videoMaster[0];
                        array_push($videoURI, $mediaURI);
                    }            
                    // create audio master playlist
                    if (strpos($mediaType, "audio") !== FALSE ) {
                        $audioMaster = createAudioMaster($matches);
                        $mediaURI = $audioMaster[0];
                        $audioMasterInfo = $audioMaster[2];
                        foreach ($videoMasterInfo as &$val) {
                            $val .= $audioMaster[1];
                        }                            
                    }
                    // create media plalist
                    $mediaPlaylist = createMediaPlaylist($file, $matches, $mediaType);
                    saveM3U8($mediaURI, $mediaPlaylist);

                    $representation = extractRep($adaptationText, $representationIdx);
                    $representationText = $representation[0];
                    $representationIdx = $representation[1];
                }
                $adaptationSet = extractAdSet($periodText, $adaptationIdx);
                $adaptationText = $adaptationSet[0];
                $adaptationIdx = $adaptationSet[1];
                $adaptationInfo = $adaptationSet[2];
            }
            $period = extractPeriod($mpdRaw, $periodIdx);
            $periodText = $period[0];
            $periodIdx = $period[1];
            $periodInfo = $period[2];
        }
        
        $masterInfo = "#EXTM3U\n"."#EXT-X-VERSION:7\n";
        for ($cnt = 0; $cnt < count($videoMasterInfo); $cnt++) {
            $masterInfo .= $videoMasterInfo[$cnt].$videoURI[$cnt]."\n";
        }
        $masterInfo .= $audioMasterInfo;
        fwrite($masterFile, $masterInfo);
        fclose($masterFile);
        $masterName = "master.m3u8";
        $masterJSON = json_encode($masterName);
        echo $masterName;
        
        /*
        // clear m3u8 files
        unlink("master.m3u8");
        foreach ($videoURI as &$playlist) {
            unlink($playlist);
        }
        */
        
        // create media plalist
        function createMediaPlaylist($file, $matches, $mediaType) {
            $header = "#EXTM3U\n"."#EXT-X-VERSION:7\n";
            //echo $header;
            // #EXT-X-TARGETDURATION:2
            $timePatternFull = "~(\d*)H(\d*)M(.*)S~";
            $timePatternSec = "~(\d*)S~";    
            $rawDuration = retrieveValue("mediaPresentationDuration", $matches);  // if no such key, ceil(all #EXTINF)!   // retrieveValue('maxSegmentDuration', hRep);
            $time = "";
            $hit = preg_match($timePatternFull, $rawDuration, $time);
            if ($hit)
            {   
                $periodDuration = ceil(floatval($time[1]) * 3600 + floatval($time[2]) * 60 + floatval($time[3]));
            }
            else
            {
                $hit = preg_match($timePatternSec, $rawDuration, $time);
                $periodDuration = intfloat($time[1]);
            }
            $maxSegmentDuration = "#EXT-X-TARGETDURATION:".$periodDuration."\n";
            //echo $maxSegmentDuration;
            // #EXT-X-MEDIA-SEQUENCE:1
            $startNumber = floatval(retrieveValue("startNumber", $matches));
            $startSequence = "#EXT-X-MEDIA-SEQUENCE:".$startNumber."\n";
            //echo $startSequence;
            // #EXT-X-PLAYLIST-TYPE:VOD                
            $streamType = retrieveValue("type", $matches);
            if ($streamType === "static") {
                $playlistType = "#EXT-X-PLAYLIST-TYPE:VOD\n";
                $end = "#EXT-X-ENDLIST";
            } elseif ($streamType === "live") {
                    $playlistType = "#EXT-X-PLAYLIST-TYPE:EVENT\n";
                    $end = "";
            }
            //echo $playlistType;
            // #EXT-X-MAP:URI="tears_of_steel_1080p_1000k_h264_dash_track1_init.mp4"
            $mapInit = '#EXT-X-MAP:URI="'.substr($file, 0, strrpos($file, "/") + 1).retrieveValue("initialization", $matches).'"'."\n";
            //echo $mapInit;
            // #EXTINF:2
            // tears_of_steel_1080p_1000k_h264_dash_track1_$Number$.m4s
            $segmentDuration = floatval(retrieveValue2("duration", $matches)) / floatval(retrieveValue("timescale", $matches));
            $numSegment = ceil($periodDuration / $segmentDuration);
            $segmentsName = retrieveValue('media', $matches);
            $segmentUnit = "";
            for ($i = 0; $i < $numSegment; $i++)
            {
                if ($i === $numSegment - 1)
                {
                  $segmentDuration = $periodDuration - $segmentDuration * ($numSegment - 1);
                }
                // #EXTINF:2.000
                $inf = '#EXTINF:'.$segmentDuration."\n";
                // tears_of_steel_1080p_1000k_h264_dash_track1_$Number$.m4s
                $segment = substr($file, 0, strrpos($file, "/") + 1).preg_replace("~\\\$.*?\\\$~", $i + $startNumber, $segmentsName)."\n";
                $segmentUnit .= $inf.$segment;
            }   
            //echo $segmentUnit;
            if (strpos($mediaType, "video") !== FALSE) {
                // #EXT-X-MLB-VIDEO-INFO:codecs="avc1.640028",width="1920",height="1080",sar="1:1",frame-duration=12288
                $media_info = '#EXT-X-MLB-VIDEO-INFO:'.'codecs="'.retrieveValue("codecs", $matches).'",'.'width="'.retrieveValue("width", $matches).'",'.'height="'.retrieveValue("height", $matches).'",'.'sar="'.retrieveValue("sar", $matches).'",'.'frame-duration='.retrieveValue("timescale", $matches)."\n";	        
            }
            if (strpos($mediaType, "audio") !== FALSE) {
                // #EXT-X-MLB-AUDIO-INFO:codecs="mp4a.40.2",audioSamplingRate="48000"
                $media_info = '#EXT-X-MLB-AUDIO-INFO:'.'codecs="'.retrieveValue('codecs', $matches).'",'.'audioSamplingRate="'.retrieveValue('audioSamplingRate', $matches).'"'."\n";
                // #EXT-X-MLB-AUDIO-CHANNEL-INFO:schemeIdUri="urn:mpeg:dash:23003:3:audio_channel_configuration:2011",value="2"
                $media_info .= '#EXT-X-MLB-AUDIO-CHANNEL-INFO:schemeIdUri="'.retrieveValue('schemeIdUri', $matches).'",'.'value="'.retrieveValue('value', $matches).'"'."\n";
            }
            //echo $media_info;
            #EXT-X-MLB-INFO:max-bw=2067007,duration=734.167
            $info = '#EXT-X-MLB-INFO:'.'max-bw='.retrieveValue('bandwidth', $matches).',duration='.$periodDuration."\n";
            //echo $info;
            //echo $end;
            $mediaPlaylist = $header.$maxSegmentDuration.$startSequence.$playlistType.$mapInit.$segmentUnit.$media_info.$info.$end;
            return $mediaPlaylist;
        
        }
        // create media playlist
        function saveM3U8($mediaURI, $mediaPlaylist) {
            $m3u8File = fopen($mediaURI, "w") or die("Unable to open file!");
            fwrite($m3u8File, $mediaPlaylist);
            fclose($m3u8File);
        }
        
?>
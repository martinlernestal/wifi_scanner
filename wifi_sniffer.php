#!/usr/bin/php
<?php

ini_set('memory_limit','1024M');

set_time_limit(120);

$scanned_wifis = fopen('./wifi_sniffer.tmp','r');

$buffered_string = "";

while ($line = fgets($scanned_wifis)) {
    $buffered_string .= $line;
}

fclose($scanned_wifis);

$chunks = preg_split("/START/", $buffered_string);

unset($chunks[0]);
$chunk_arrays = array();
$current_chunk_array = array();
$current_address = array();
$latest_registered_time = "";

foreach($chunks as $current_chunk){

    preg_match("/([0-9]{4}\-[0-9]{2}\-[0-9]{2}\s[0-9]{2}\:[0-9]{2}\:[0-9]{2})/", $current_chunk, $found_time, PREG_OFFSET_CAPTURE);

    if(!empty($found_time[0][0])){
        $latest_registered_time = $latest_registered_time;
    } else if(empty($latest_registered_time)){
        $latest_registered_time = date("Y-m-d H:i:s");
    }

    preg_match_all("/Address: (([0-9a-zA-Z]{2}\:){5}[0-9a-zA-Z]{2})/", $current_chunk, $match_address, PREG_PATTERN_ORDER);
    preg_match_all("/Quality=([0-9]{2})/", $current_chunk, $match_quality, PREG_PATTERN_ORDER);
    preg_match_all("/ESSID:\"(.*)\"/", $current_chunk, $match_ssid, PREG_PATTERN_ORDER);
    preg_match_all("/Signal level=(\-?[0-9]+)/", $current_chunk, $match_signal, PREG_PATTERN_ORDER);

    for($i = 0; $i < (count($match_address[1])-1); $i++){

        $current_address['address'] = $match_address[1][$i];
        $current_address['ssid'] = $match_ssid[1][$i];

        if(empty($found_time[0][0])){
            $found_time[0][0] = $latest_registered_time;
        }

        $current_address['found_time'][$found_time[0][0]] = array(
                                                    "quality" => $match_quality[1][$i],
                                                    "signal_strength" => $match_signal[1][$i]
                                                );

        array_push($chunk_arrays, $current_address);
        unset($current_address);

    }
}

// det är denna arrayn som får bestämma vilka addresser som redan förekommit
$control_array_address = array();

if(!empty($chunk_arrays)){

    $old_scanned_wifis = json_decode(file_get_contents("wifi_sniffer.json"), true);

    if(!empty($old_scanned_wifis)){

        // här fyller man på alla found_times till dom man redan har:
        // gör alla adrresser vi har sammanlagt unika
        foreach($chunk_arrays as $scanned_wifi){
            array_push($control_array_address, $scanned_wifi['address']);
        }
        foreach($old_scanned_wifis as $scanned_wifi){
            array_push($control_array_address, $scanned_wifi['address']);
        }

        $control_array_address = array_unique($control_array_address);

        // går igenom och lägger till alla nyinlästa wifis foundtimes i dom gamla
        foreach($old_scanned_wifis as $key => $scanned_wifi){
            foreach($chunk_arrays as $chunk_wifi){
                if($chunk_wifi['address'] == $scanned_wifi['address']){

                    // det är denna arrayn som styr om man redan har lagt in addressen eller inte
                    $old_scanned_wifis[$key]['found_time'] = array_merge($old_scanned_wifis[$key]['found_time'], $chunk_wifi['found_time']); 
                }
            }
        }

        // pusha på helt "blint" alla  nya som man precis skannat, iom. att dom lägger
            // sig längst bak så komemr man ta alla större först
        foreach($chunk_arrays as $current_wifi){
            array_push($old_scanned_wifis, $current_wifi);
        }

        // här sorteras alla unika addresser ut, så man bara får en av varje
        $merge = array();
        foreach($old_scanned_wifis as $current_wifi){
            if(in_array($current_wifi['address'], $control_array_address)){
                array_push($merge, $current_wifi);
                unset($control_array_address[array_search($current_wifi['address'], $control_array_address)]);
            }
        }
    } else {

        foreach($chunk_arrays as $scanned_wifi){
            array_push($control_array_address, $scanned_wifi['address']);
        }

        $control_array_address = array_unique($control_array_address);
        
        // sortera ut bara dom unika... 
            // rättare sagt, lägger bara till den första man hittar

        $merge = array();

        foreach($chunk_arrays as $current_chunk){

            if(in_array($current_chunk['address'], $control_array_address)){
                array_push($merge, $current_chunk);
                unset($control_array_address[array_search($current_chunk['address'], $control_array_address)]);
            }
        }
    }

    $merge_to_json = json_encode($merge);

    $file = fopen("wifi_sniffer.json", "w") or die("unable to open file");
    fwrite($file, $merge_to_json);
    fclose($file);
}

if(!empty($buffered_string)){
    // töm den temporära filen
    fclose(fopen('wifi_sniffer.tmp','w'));
}
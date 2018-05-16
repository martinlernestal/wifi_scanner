#/bin/bash


#
#   Change wireless card interface to match os (second argument to iwlist)
#   


while :
do
    for value in {1..5}
    do
    clear
    echo "Scanning for wifis..."
    ls -la | grep --color "wifi_sniffer"
    echo 'START' >> wifi_sniffer.tmp
    date +'%Y-%m-%d %H:%M:%S' >> wifi_sniffer.tmp
    sudo iwlist wlp3s0 scan | grep 'ESSID\|Address\|Quality\|Signal\|Level' >> wifi_sniffer.tmp
    done
    ./wifi_sniffer.php
done
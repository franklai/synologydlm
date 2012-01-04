#!/usr/bin/php
<?php

if (!class_exists('Common')) {
class Common {
    // return substring that match prefix and suffix
    // returned string contains prefix and suffix
    static function getSubString($string, $prefix, $suffix) {
        $start = strpos($string, $prefix);
        if ($start === FALSE) {
            echo "cannot find prefix, string:[$string], prefix[$prefix]\n";
            return $string;
        }

        $end = strpos($string, $suffix, $start);
        if ($end === FALSE) {
            echo "cannot find suffix\n";
            return $string;
        }

        if ($start >= $end) {
            return $string;
        }

        return substr($string, $start, $end - $start + strlen($suffix));
    }

    static function getFirstMatch($string, $pattern) {
        if (1 === preg_match($pattern, $string, $matches)) {
            return $matches[1];
        }
        return FALSE;
    }

    static function convertSize($string) {
        $pattern = '/([0-9\.]+ *([a-zA-Z]*))/';
        $number;
        $unit;
        $unitTable = array('Bytes', 'KB', 'MB', 'GB', 'TB');

        if (1 === preg_match($pattern, $string, $matches)) {
            $number = $matches[1];
            $unit = $matches[2];
        }

        foreach ($unitTable as $idx => $unitStr) {
            if (0 === strcasecmp($unit, $unitStr)) {
                $unitSize = pow(1024, $idx);
                break;
            }
        }

        $size = floatval($number) * $unitSize;

        return round($size);
    }
}
}

class TorrentSearchDmhy {
    private $pagePrefix = 'http://share.dmhy.org';
    private $qurl = 'http://share.dmhy.org/topics/list?keyword=';

    public function __construct() {
    }
    public function prepare($curl, $query) {
        $url = $this->qurl . urlencode($query);
        curl_setopt($curl, CURLOPT_URL, $url);
    }

    private function getTableBody($response) {
        $start = strpos($response, '<tbody>');
        if ($start === FALSE) {
            echo "Failed to find <tbody>\n";
            return FALSE;
        }

        $end = strpos($response, '</tbody>');
        if ($end === FALSE) {
            echo "Failed to find </tbody>\n";
            return FALSE;
        }

        if ($start >= $end) {
            echo "start: $start >= end: $end\n";
            return FALSE;
        }

        return substr($response, $start, $end - $start);
    }

    private function getPageLink($string) {
        $pattern = '/a href="([^"]+)"/';

        return Common::getFirstMatch($string, $pattern);
    }
    private function getTopicTitle($string) {
        return trim(strip_tags(
            Common::getSubString($string, '<a href="/topics/view', '</a>')
        ));
    }
    private function getHash($string) {
        // magnet:?xt=urn:btih:MDPNCQGNZB5OKUJ6RX3JDZ4G4V6ILDLI
        $pattern = '/\/hash_id\/([^"]+)"/';
        return Common::getFirstMatch($string, $pattern);
    }
    private function getCategory($string) {
        return trim(strip_tags(
            Common::getSubString($string, '<font', '</font>')
        ));
    }
    private function combineDownloadLink($title, $hash) {
        return 'magnet:?xt=urn:btih:' . $hash . '&dn=' . urlencode($title);
    }
    private function getDownloadLink($string) {
        $pattern = '/"(magnet:[^"]+)"/';
        return Common::getFirstMatch($string, $pattern);
    }
    private function getSeeds($string) {
        return trim(strip_tags(
            Common::getSubString($string, '<span', '</span>')
        ));
    }
    private function getLeechs($string) {
        return trim(strip_tags(
            Common::getSubString($string, '<span', '</span>')
        ));
    }
    private function getSize($string) {
        return Common::convertSize($string);
    }
    private function getDatetime($string) {
        $pattern = '/<span style="display: none;">([^<]+)</';
        return Common::getFirstMatch($string, $pattern);
    }

    private function getInfoFromItem($item) {
        // parse string ($item) to get
        // title
        // download: link, we should grab hash id to magnet link
        // hash
        // size
        // page: link of this article
        // date
        // seeds
        // leechs
        // category
        $info = array();

        if (FALSE === strpos($item, '/hash_id/')) {
            return FALSE;
        }

        $tdArray = explode('<td ', $item);
        // 0:
        // 1: date
        // 2: category
        // 3: title and page link
        // 4: torrent link (need login)
        // 5: magnet link
        // 6: size
        // 7: seeds
        // 8: leechs
        // 9: // completed
        // 10: // publisher

        $titlePrefix = '<a href="/topics/view/';
        $titleSuffix = '</a>';
        $titleRawString = Common::getSubString($tdArray[3], $titlePrefix, $titleSuffix);

        $title = $this->getTopicTitle($titleRawString);

        $info['page'] = $this->pagePrefix . $this->getPageLink($titleRawString);
        $info['title'] = $title;
        $info['category'] = $this->getCategory($tdArray[2]);
        $hash = $this->getHash($tdArray[4]);
        $info['hash'] = $hash;
        $info['download'] = $this->getDownloadLink($tdArray[5]);
        $info['seeds'] = $this->getSeeds($tdArray[7]);
        $info['leechs'] = $this->getLeechs($tdArray[8]);

        $info['size'] = $this->getSize($tdArray[6]);
        $info['datetime'] = $this->getDatetime($tdArray[1]);

        return $info;
    }

    public function parse($plugin, $response) {
        // get table body first!
        $tbody = $this->getTableBody($response);

        $trArray = explode('</tr>', $tbody);

        $infoList = array();

        foreach ($trArray as $item) {
            $info = $this->getInfoFromItem($item);

            if ($info) {
                $plugin->addResult(
                    $info['title'], $info['download'], $info['size'],
                    $info['datetime'], $info['page'], $info['hash'],
                    $info['seeds'], $info['leechs'], $info['category']
                );
            }
        }
        return count($infoList);
    }
}

if (!debug_backtrace()) {
    function init_curl() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

        return $curl;
    }

    class TestObj {
        function addResult($title, $download, $size,
                $datetime, $page, $hash,
                $seeds, $leechs, $category) {

            echo $title;
            echo "\n";
            echo "\t" .'datetime:'."\t". $datetime;
            echo "\n";
            echo "\t" .'seeds:'."\t". $seeds."\t".'leechs:'."\t".$leechs;
            echo "\n";
            echo "\n";
        }
    }

    $curl = init_curl();

    $module = 'TorrentSearchDmhy';
    $query = 'gintama';


    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance();
    $obj->prepare($curl, $query);

    $testObj = new TestObj();

    echo "curl_exec...\n";
    $response = curl_exec($curl);

    $total = $obj->parse($testObj, $response);
}

// vim: expandtab ts=4
?>


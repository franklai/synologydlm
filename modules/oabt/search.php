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


class TorrentSearchOabt {
    private $pagePrefix = 'http://oabt.org/';
    private $qurl = 'http://oabt.org/?topic_title=';

    public function __construct() {
    }
    public function prepare($curl, $query) {
        $url = $this->qurl . urlencode($query);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
    }

    private function getTableBody($response) {
        $prefix = '<div class="toplist">';
        $suffix = '<div id="pages_btns">';
        return Common::getSubString($response, $prefix, $suffix);
    }

    private function getPageLink($string) {
        $pattern = '/a href="([^"]+)"/';

        return Common::getFirstMatch($string, $pattern);
    }
    private function getTopicTitle($string) {
        return trim(strip_tags(
            Common::getSubString($string, '<a href="show.php', '</a>')
        ));
    }
    private function getHash($string) {
        // magnet:?xt=urn:btih:MDPNCQGNZB5OKUJ6RX3JDZ4G4V6ILDLI
        $pattern = '/urn:btih:([0-9a-zA-Z]+)/';
        return Common::getFirstMatch($string, $pattern);
    }
    private function getCategory($string) {
        return trim(strip_tags(
            Common::getSubString($string, '<a href=', '</a>')
        ));
    }
    private function getDownloadLink($string) {
        $pattern = '/a href="(magnet:[^"]+)" /';
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

        if (FALSE === strpos($item, '<td class="name magTitle"')) {
            return FALSE;
        }

        $tdArray = explode('<td ', $item);
        // 0:
        // 1: category
        // 2: title
        // 3: magnet link
        // 4: time
        // 5: size
        // 6: download count
        $title = $this->getTopicTitle($tdArray[2]);
        $info['page'] = $this->pagePrefix . $this->getPageLink($tdArray[2]);
        $info['title'] = $title;
        $info['category'] = $this->getCategory($tdArray[1]);
        $info['hash'] = $this->getHash($tdArray[3]);
        $info['download'] = $this->getDownloadLink($tdArray[3]);
        $info['size'] = $this->getSize($tdArray[5]);
        $info['datetime'] = '2000/01/01 00:00:00';
        $info['seeds'] = 0;
        $info['leechs'] = 0;
 
        return $info;
    }

    public function parse($plugin, $response) {
        // get table body first!
        $tbody = $this->getTableBody($response);

        $trArray = explode('<tr>', $tbody);

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
        return count($trArray);
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
            echo "\t" .'datetime:'."\t". $datetime . "\n";
            echo "\t" .'download:'."\t". $download. "\n";
            echo "\t" .'size:'."\t". $size. "\n";
            echo "\t" .'seeds:'."\t". $seeds . "\n";
            echo "\t" .'leechs:'."\t". $leechs . "\n";
            echo "\t" .'page:'."\t". $page . "\n";
            echo "\t" .'category:'."\t". $category . "\n";
            echo "\n";
            echo "\n";
        }
    }

    $curl = init_curl();

    $module = 'SynoDLMSearchOabt';
    $query = 'mita';

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


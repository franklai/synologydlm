<?php

if (!class_exists('Common')) {
    class Common
    {
        // return substring that match prefix and suffix
        // returned string contains prefix and suffix
        public static function getSubString($string, $prefix, $suffix)
        {
            $start = strpos($string, $prefix);
            if ($start === false) {
                echo "cannot find prefix, string:[$string], prefix[$prefix]\n";
                return $string;
            }

            $end = strpos($string, $suffix, $start);
            if ($end === false) {
                echo "cannot find suffix\n";
                return $string;
            }

            if ($start >= $end) {
                return $string;
            }

            return substr($string, $start, $end - $start + strlen($suffix));
        }

        public static function getFirstMatch($string, $pattern)
        {
            if (1 === preg_match($pattern, $string, $matches)) {
                return $matches[1];
            }
            return false;
        }

        public static function convertSize($string)
        {
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

class TorrentSearchLastFm
{
    private $pagePrefix = 'https://www.last.fm/';
    private $qurl = 'https://www.last.fm/search/tracks?q=';

    public function __construct()
    {
    }
    public function prepare($curl, $query, $username = null, $password = null)
    {
        $url = $this->qurl . urlencode($query);
        curl_setopt($curl, CURLOPT_URL, $url);
    }

    private function getTableBody($response)
    {
        $start = strpos($response, '<table class="torrent_table"');
        if ($start === false) {
            echo "Failed to find <table>";
            return false;
        }

        $end = strpos($response, '</table>');
        if ($end === false) {
            echo "Failed to find </table>";
            return false;
        }

        if ($start >= $end) {
            echo "start: $start >= end: $end\n";
            return false;
        }

        return substr($response, $start, $end - $start);
    }

    private function getPageLink($string)
    {
        $pattern = '/<a href="(torrents.php\?id=[0-9]+\&amp;torrentid=[0-9]+)"/';
        return htmlspecialchars_decode(
            Common::getFirstMatch($string, $pattern),
            ENT_QUOTES
        );
    }
    private function getTitle($string)
    {
        return htmlspecialchars_decode(trim(strip_tags(
            Common::getSubString(str_replace("\t\t\t", ' ', $string), '</span>', '<br ')
        )), ENT_QUOTES);
    }
    private function getCategory($string)
    {
        return trim(strip_tags(
            Common::getSubString($string, '<a href=', '</a>')
        ));
    }
    private function getDownloadLink($string)
    {
        $pattern = '/a href="([^"]+)" title="Download"/';
        return htmlspecialchars_decode(
            Common::getFirstMatch($string, $pattern),
            ENT_QUOTES
        );
    }
    private function getSeeds($string)
    {
        $pattern = '/>([0-9]+)</';
        return Common::getFirstMatch($string, $pattern);
    }
    private function getLeechs($string)
    {
        $pattern = '/>([0-9]+)</';
        return Common::getFirstMatch($string, $pattern);
    }
    private function getSize($string)
    {
        return Common::convertSize(
            Common::getSubString($string, '>', '</td')
        );
    }
    private function getDatetime($string)
    {
        // JAN 11 2011, 08:47
        $pattern = '/title="([^"]+)"/';
        $str = Common::getFirstMatch($string, $pattern);
        $date = DateTime::createFromFormat('M d Y, H:m', $str);
        return $date->getTimestamp();
    }

    private function getInfoFromItem($item)
    {
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

        if (false === strpos($item, 'action=download')) {
            return false;
        }

        $tdArray = explode('<td', $item);
        // 0:
        // 1: category
        // 2:
        // 3: title, torrent link
        // 3:
        // 4:
        // 5: date
        // 6: size
        // 7:
        // 8: seeds
        // 9: leechs

        $info['title'] = $this->getTitle($tdArray[3]);
        $info['page'] = $this->pagePrefix . $this->getPageLink($tdArray[3]);
        $info['category'] = $this->getCategory($tdArray[1]);
        $info['hash'] = md5($info['page']);
        $info['download'] = $this->pagePrefix . $this->getDownloadLink($tdArray[3]);
        $info['seeds'] = $this->getSeeds($tdArray[8]);
        $info['leechs'] = $this->getLeechs($tdArray[9]);

        $info['size'] = $this->getSize($tdArray[6]);
        $info['datetime'] = $this->getDatetime($tdArray[5]);

        return $info;
    }

    public function parse($plugin, $response)
    {
        $one_line = str_replace("\n", "", $response);

        $pattern = '/data-youtube-url="(.+?)"\s*data-track-name="(.+?)".*?data-artist-name="(.+?)"/';
        $ret = preg_match_all($pattern, $one_line, $matches, PREG_SET_ORDER);
        if ($ret > 0) {
            foreach ($matches as $item) {
                $url = $item[1];
                $title = $item[2];
                $artist = $item[3];

                $plugin->addResult(
                    "$artist - $title", $url, 0,
                    0, 0, md5($url),
                    0, 0, "music"
                );
            }

            return $ret;
        }

        return 0;
    }
}

// if (!debug_backtrace()) {
if (basename($argv[0]) === basename(__FILE__)) {
    function init_curl()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);

        return $curl;
    }

    class TestObj
    {
        public function addResult($title, $download, $size,
            $datetime, $page, $hash,
            $seeds, $leechs, $category) {

            echo $title;
            echo "\n";
            echo "\t" . 'url:' . "\t" . $download;
            echo "\n";
            echo "\n";
        }
    }

    $curl = init_curl();

    $module = 'TorrentSearchLastFm';
    $query = 'style';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance();

    $obj->prepare($curl, $query);
    $testObj = new TestObj();
    echo "curl_exec...\n";
    $response = curl_exec($curl);

    $total = $obj->parse($testObj, $response);
}

// vim: expandtab ts=4

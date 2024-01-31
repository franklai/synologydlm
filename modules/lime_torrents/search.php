<?php

class LimeTorrentsCommon
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
        $number = 0;
        $unit = '';
        $unitSize = 0;
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

    public static function convertSeedLeech($string)
    {
        $replaced = str_replace(',', '', $string);
        return intval($replaced);
    }
}

class TorrentSearchLimeTorrents
{
    // https://www.limetorrents.lol/search/all/aquaman/seeds/1/
    private $site = 'https://www.limetorrents.cc';
    private $prefix = 'https://www.limetorrents.lol/search/all/';
    private $suffix = '/seeds/1/';

    public function __construct()
    {
    }
    public function prepare($curl, $query)
    {
        $url = $this->prefix . urlencode($query) . $this->suffix;
        curl_setopt($curl, CURLOPT_URL, $url);
    }

    public function parse($plugin, $response)
    {
        $one_line = str_replace("\n", "", $response);

        $pattern = '/class="tt-name"><a href="(http:\/\/itorrents[^"]+)".*?href="([^"]+)".*?>([^<]+)<\/a>.*?>([0-9\.]+ [KMGT]B)<.*?"tdseed">([0-9,]+)<.*?"tdleech">([0-9,]+)</';
        $ret = preg_match_all($pattern, $one_line, $matches, PREG_SET_ORDER);
        if ($ret > 0) {
            foreach ($matches as $item) {
                $torrentLink = $item[1];
                $pageLink = $this->site . $item[2];
                $title = $item[3];
                $rawSize = $item[4];
                $size = LimeTorrentsCommon::convertSize($rawSize);
                $rawSeed = $item[5];
                $seed = LimeTorrentsCommon::convertSeedLeech($rawSeed);
                $rawLeech = $item[6];
                $leech = LimeTorrentsCommon::convertSeedLeech($rawLeech);

                $datetime = '2000/01/01 00:00:00';

                // title, download, size
                // datetime, page, hash
                // seeds, leechs, category
                $plugin->addResult(
                    $title, $torrentLink, $size,
                    $datetime, $pageLink, $pageLink,
                    $seed, $leech, ''
                );
            }
        }

        return 0;
    }
}

if (!debug_backtrace()) {
    function init_curl()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

        return $curl;
    }

    class TestObj
    {
        public function addResult($title, $download, $size,
            $datetime, $page, $hash,
            $seeds, $leechs, $category) {

            echo $title;
            echo "\n";
            echo "\t" . 'datetime:' . "\t" . $datetime;
            echo "\n";
            echo "\t" . 'seeds:' . "\t" . $seeds . "\t" . 'leechs:' . "\t" . $leechs;
            echo "\n";
            echo "\n";
        }
        public function addRSSResults($rss)
        {
            $pattern = '/<title><!\[CDATA\[(.+?)\]\]>/';
            $ret = preg_match_all($pattern, $rss, $matches);
            if ($ret > 0) {
                var_dump($matches);
                return $matches[1];
            } else {
                echo "no\n";
            }
        }
    }

    $curl = init_curl();

    $module = 'TorrentSearchLimeTorrents';
    $query = 'aquaman';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance();
    $obj->prepare($curl, $query);

    $testObj = new TestObj();

    echo "curl_exec...\n";
    $response = curl_exec($curl);
    echo "response: $response\n";

    $total = $obj->parse($testObj, $response);
}

// vim: expandtab ts=4

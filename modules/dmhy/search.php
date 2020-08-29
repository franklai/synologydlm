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

class TorrentSearchDmhy
{
    private $pagePrefix = 'https://share.dmhy.org';
    private $qurl = 'https://share.dmhy.org/topics/rss/rss.xml?keyword=';

    public function __construct()
    {
    }
    public function prepare($curl, $query)
    {
        $url = $this->qurl . urlencode($query);
        curl_setopt($curl, CURLOPT_URL, $url);
    }

    public function parse($plugin, $response)
    {
        return $plugin->addRSSResults($response);
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

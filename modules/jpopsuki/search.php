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

class TorrentSearchJpopsuki {
    private $pagePrefix = 'http://jpopsuki.eu/';
    private $qurl = 'http://jpopsuki.eu/ajax.php?section=torrents&searchtags=&tags_type=0&order_by=s3&order_way=desc&disablegrouping=1&searchstr=';
	private $loginURL = 'http://jpopsuki.eu/login.php';
	private $cookieFile= '/tmp/jpopsuki.cookie';

    public function __construct() {
    }
    public function prepare($curl, $query, $username=NULL, $password=NULL) {
        $url = $this->qurl . urlencode($query);
        curl_setopt($curl, CURLOPT_URL, $url);

        if ($username !== NULL && $password !== NULL) {
            $this->VerifyAccount($username, $password);
            curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookieFile);
        }
    }

    public function GetCookie() 
    {
        return $this->cookieFile;
    }
    
    public function VerifyAccount($username, $password) {
        $ret = FALSE;

        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }

        $postData = sprintf("username=%s&password=%s", $username, $password);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, TRUE);
        curl_setopt($curl, CURLOPT_URL, $this->loginURL);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($curl);
        curl_close($curl);

        if (FALSE === strpos($result, '302 Found')) {
            // login success will redirect
            $ret = TRUE;
        }

        return $ret;
    }

    private function getTableBody($response) {
        $start = strpos($response, '<table class="torrent_table"');
        if ($start === FALSE) {
            echo "Failed to find <table>";
            return FALSE;
        }

        $end = strpos($response, '</table>');
        if ($end === FALSE) {
            echo "Failed to find </table>";
            return FALSE;
        }

        if ($start >= $end) {
            echo "start: $start >= end: $end\n";
            return FALSE;
        }

        return substr($response, $start, $end - $start);
    }

    private function getPageLink($string) {
        $pattern = '/<a href="(torrents.php\?id=[0-9]+\&amp;torrentid=[0-9]+)"/';
        return htmlspecialchars_decode(
            Common::getFirstMatch($string, $pattern), 
            ENT_QUOTES
        );
    }
    private function getTitle($string) {
        return htmlspecialchars_decode(trim(strip_tags(
            Common::getSubString(str_replace("\t\t\t", ' ', $string), '</span>', '<br ')
        )), ENT_QUOTES);
    }
    private function getCategory($string) {
        return trim(strip_tags(
            Common::getSubString($string, '<a href=', '</a>')
        ));
    }
    private function getDownloadLink($string) {
        $pattern = '/a href="([^"]+)" title="Download"/';
        return htmlspecialchars_decode(
            Common::getFirstMatch($string, $pattern),
            ENT_QUOTES
        );
    }
    private function getSeeds($string) {
        $pattern = '/>([0-9]+)</';
        return Common::getFirstMatch($string, $pattern);
    }
    private function getLeechs($string) {
        $pattern = '/>([0-9]+)</';
        return Common::getFirstMatch($string, $pattern);
    }
    private function getSize($string) {
        return Common::convertSize(
            Common::getSubString($string, '>', '</td')
        );
    }
    private function getDatetime($string) {
        // JAN 11 2011, 08:47
        $pattern = '/title="([^"]+)"/';
        $str = Common::getFirstMatch($string, $pattern);
        $date = DateTime::createFromFormat('M d Y, H:m', $str);
        return $date->getTimestamp();
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

        if (FALSE === strpos($item, 'action=download')) {
            return FALSE;
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

// if (!debug_backtrace()) {
if (basename($argv[0]) === basename(__FILE__)) {
    function init_curl() {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);

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

    $module = 'TorrentSearchJpopsuki';
    $query = 'amuro';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance();

    $ini_file = 'setting.conf';
    $setting = parse_ini_file($ini_file);
    $username = $setting['username'];
    $password = $setting['password'];

    $obj->prepare($curl, $query, $username, $password);
    $testObj = new TestObj();
    echo "curl_exec...\n";
    $response = curl_exec($curl);

    $total = $obj->parse($testObj, $response);
}

// vim: expandtab ts=4
?>
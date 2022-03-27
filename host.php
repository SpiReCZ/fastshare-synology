<?php

class SynologyFastshareFree
{
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;

    public function __construct($Url, $Username, $Password, $HostInfo)
    {
        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;
    }

    public function GetDownloadInfo()
    {
        $ret = $this->Verify(FALSE);
        if ($ret == FALSE)
            return array(DOWNLOAD_ERROR => LOGIN_FAIL);

        $ret = $this->getFreeLink();
        if ($ret == FALSE)
            return array(DOWNLOAD_ERROR => ERR_FILE_NO_EXIST);

        $ret = $this->getFileLink($ret);
        if ($ret == FALSE) {
            return array(DOWNLOAD_ERROR => ERR_TRY_IT_LATER);
        } elseif (array_key_exists(DOWNLOAD_COUNT, $ret)) {
            return $ret;
        }

        $ret = $this->downloadTest($ret);
        if ($ret == FALSE) {
            return array(DOWNLOAD_ERROR => ERR_TRY_IT_LATER);
        }

        return $ret;
    }

    public function Verify($ClearCookie)
    {
        return USER_IS_FREE;
    }

    private function downloadTest($DownloadInfo)
    {
        $timeout = mt_rand(5, 10);
        //sleep(mt_rand(0, 1) + mt_rand() / mt_getrandmax());

        $curlsession = curl_init($DownloadInfo[DOWNLOAD_URL]);
        curl_setopt($curlsession, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
        curl_setopt($curlsession, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curlsession, CURLOPT_TIMEOUT, $timeout);
        //curl_setopt($curlsession, CURLOPT_FAILONERROR, FALSE);
        $response = curl_exec($curlsession);

        $size = curl_getinfo($curlsession, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($curlsession);
        if ($size > 0) {
            return $DownloadInfo;
        } else {
            // Wait 60 seconds then query this host plugin again
            // Passing download url is required
            return array(DOWNLOAD_COUNT => 60, DOWNLOAD_URL => $this->Url, INFO_NAME => trim($this->HostInfo[INFO_NAME]), DOWNLOAD_ISQUERYAGAIN => 1);
        }
    }

    private function getFileLink($FreeUrl)
    {
        $curlsession = curl_init($FreeUrl);
        $headers = array();
        curl_setopt($curlsession, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
        curl_setopt($curlsession, CURLOPT_HEADER, TRUE);
        curl_setopt($curlsession, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curlsession, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );
        //curl_setopt($curlsession, CURLOPT_FOLLOWLOCATION, TRUE);
//			curl_setopt($curlsession, CURLOPT_VERBOSE, TRUE);
        //sleep(mt_rand(0,5));
        $response = curl_exec($curlsession);

        $httpcode = curl_getinfo($curlsession, CURLINFO_HTTP_CODE);
        //$fileSize = round(curl_getinfo($curlsession, CURLINFO_CONTENT_LENGTH_DOWNLOAD) / 1024);

        curl_close($curlsession);
        if ($response == FALSE) {
            return FALSE;
        }

        if ($httpcode == 200) {
            return array(DOWNLOAD_COUNT => 60, DOWNLOAD_URL => $this->Url, INFO_NAME => trim($this->HostInfo[INFO_NAME]));
        }

        if ($httpcode == 302) {
            $fileUrl = $headers["location"][0];

            //https://data13.fastshare.cz/download_free.php?h=do294849jkfj2994Ufjks
            $urlMatches = preg_match("/(.*download_free.php.*)/", $fileUrl, $matches);
            if ($urlMatches != 1) {
                return array(DOWNLOAD_COUNT => 60, DOWNLOAD_URL => $this->Url, INFO_NAME => trim($this->HostInfo[INFO_NAME]));
            } else {
                return array(DOWNLOAD_URL => $fileUrl);
            }
        }

        return FALSE;
    }

    private function getFreeLink()
    {
        $curlsession = curl_init($this->Url);
        curl_setopt($curlsession, CURLOPT_HEADER, FALSE);
        curl_setopt($curlsession, CURLOPT_RETURNTRANSFER, TRUE);
//			curl_setopt($curlsession, CURLOPT_VERBOSE, TRUE);
        $response = curl_exec($curlsession);
        curl_close($curlsession);

        if ($response == FALSE)
            return FALSE;

        //$result = preg_match("/<a href=\"(.*download.php.*)\">/", $response, $matches);

        $result = preg_match("/<a .*href=\"(.*\/free\/.*)\" .*\">/", $response, $matches);
        if ($result != 1)
            return FALSE;

        return "https://fastshare.cz" . $matches[1];
    }

}

?>

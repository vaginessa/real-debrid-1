<?php

namespace RealDebrid;

use RealDebrid\Exception\ForbiddenException;

class Torrent extends Base {
    public static $HOSTER_UPTOBOX = 'utb';
    public static $HOSTER_1FICHIER = '1f';
    public static $HOSTER_MEGA = 'mega';

    /**
     * Add torrent
     *
     * @param string $magnet Magnet link
     * @param int $splitting_size Splitting size
     * @param string $hoster Torrent hoster
     * @return bool TRUE torrent has been successfully added; FALSE otherwise
     * @throws ForbiddenException User is not authenticated
     */
    public function add($magnet, $splitting_size = 50, $hoster = 'utb') {
        if(!$this->is_authenticated())
            throw new ForbiddenException;

        // Convert
        $id = $this->convert($magnet, $splitting_size, $hoster);

        // Error at torrent convertion
        if(is_null($id))
            return false;

        // Start
        $this->start($id);

        return true;
    }

    /**
     * Remove torrent from list
     *
     * @param string $torrent_id Torrent ID
     * @throws ForbiddenException User is not authenticated
     */
    public function delete($torrent_id) {
        if(!$this->is_authenticated())
            throw new ForbiddenException;

        // Remove
        CURL::exec('torrents?del=' . $torrent_id);
    }

    /**
     * Return torrents status
     *
     * @return \stdClass Torrents status
     * @throws ForbiddenException User is not authenticated
     */
    public function status() {
        if(!$this->is_authenticated())
            throw new ForbiddenException;

        $status = CURL::exec('ajax/torrent.php?action=status_a');

        return json_decode($status);
    }

    /**
     * Test if the given torrent already exists in the user torrent list
     *
     * @param $torrent_name Torrent name
     * @return bool TRUE if it exists; FALSE otherwise
     * @throws ForbiddenException User is not authenticated
     */
    public function exists($torrent_name) {
        if(!$this->is_authenticated())
            throw new ForbiddenException;

        $status = $this->status();
        $torrent_name = self::strtotorrentname($torrent_name);

        foreach($status->list as $torrent) {
            if($torrent->name == $torrent_name)
                return true;
        }

        return false;
    }

    /**
     * Convert the torrent
     *
     * @param string $magnet Magnet link
     * @param int $splitting_size Splitting size
     * @param string $hoster Torrent hoster
     * @return int|null The torrent ID; or NULL if an error occurred
     * @throws \Exception
     */
    private function convert($magnet, $splitting_size, $hoster) {
        $curl_opts[CURLOPT_REFERER] = CURL::$API . 'torrents';
        $curl_opts[CURLOPT_POST] = true;
        $curl_opts[CURLOPT_POSTFIELDS] = array(
            'magnet' => $magnet,
            'splitting_size' => $splitting_size,
            'hoster' => $hoster
        );
        $curl_opts[CURLOPT_HTTPHEADER] = array('Content-Type: multipart/form-data');

        $resp = CURL::exec('torrents', $curl_opts);
        if(preg_match('/torrent_files.php\?id=([A-Za-z0-9]+)/', $resp, $matches))
            return $matches[1];
        return null;
    }

    /**
     * Start torrent
     *
     * @param int $id Torrent ID
     * @throws \Exception
     */
    private function start($id) {
        $curl_opts[CURLOPT_REFERER] = CURL::$API . 'torrents';
        $curl_opts[CURLOPT_POST] = true;
        $curl_opts[CURLOPT_POSTFIELDS] = 'files_unwanted=&start_torrent=1';
        $curl_opts[CURLOPT_HTTPHEADER] = array('Content-Type: application/x-www-form-urlencoded');

        CURL::exec('ajax/torrent_files.php?id=' . $id, $curl_opts);
    }

    /**
     * Convert the string into a Real-Debrid torrent name
     *
     * @param string $str The input string
     * @return string The converted string
     */
    public static function strtotorrentname($str) {
        $str = trim($str);
        $str = str_replace(' ', '.', $str);
        $str = preg_replace('/^(.*)\[.*?\]$/', '$1', $str);

        return $str;
    }
}
<?php
/**
 * Author: Bow <3
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

class BattleMetrics {

    private $bearer;

    /**
     * BattleMetrics constructor.
     * @param string $bearer
     */
    public function __construct($bearer)
    {
        $this->bearer = $bearer;
    }

    /**
     * @param $pid - PlayerID, in the format: 76561198036091748
     * @return array|bool - Array of BM ID's due to their duplicate bug, or false if no player found.
     */
    public function GetPlayerBattleMetricsID($pid) {
        $pid = (int) trim($pid);

        $post = array("data" => array(array("type" => "identifier", "attributes" => array("type" => "steamID", "identifier" => "$pid"))));
        $result = $this->BattlemetricsPOST("https://api.battlemetrics.com/players/match", $post);

        $ids = [];

        if(!$result) {
            return false;
        } else {
            if(isset($result["data"])) {
                foreach($result["data"] as $bm) {
                    array_push($ids, $bm["relationships"]["player"]["data"]["id"]);
                }
            }
            return $ids;
        }
    }

    /**
     * @param $pid - PlayerID, in the format: 76561198036091748
     * @return array|bool - Array of BM Profiles, or false if no player found, may be duplicates due to a bug.
     */
    public function GetPlayerProfile($pid) {
        $pid = (int) trim($pid);

        $post = array("data" => array(array("type" => "identifier", "attributes" => array("type" => "steamID", "identifier" => "$pid"))));
        $result = $this->BattlemetricsPOST("https://api.battlemetrics.com/players/match", $post);

        if(!$result) {
            return false;
        } else {
            if(isset($result["data"])) {
                return $result["data"];
            } else {
                return false;
            }
        }
    }

    public function GetPlayerBanReasons($id) {
        $id = (int) trim($id);

        $result = $this->BattlemetricsGET("https://api.battlemetrics.com/bans?filter[player]=$id&include=player,user");

        $bans = [];

        if(!$result) {
            return false;
        } else {
            if(isset($result["data"])) {
                $staff = $this->GetBanningAdmin($result["included"]);

                foreach($result["data"] as $ban) {
                    $b = $ban["attributes"];
                    if(isset($ban["relationships"]["user"])) {
                        $admin = ($ban["relationships"]["user"]["data"]["id"] != null ? $staff[$ban["relationships"]["user"]["data"]["id"]] : "SYSTEM");
                    } else {
                        $admin = "SYSTEM";
                    }

                    $reason = $this->ReplaceBanDuration($b["reason"], $b["timestamp"], $b["expires"]);
                    array_push($bans, array("start" => $b["timestamp"], "expires" => $b["expires"], "admin" => $admin, "reason" => $reason));
                }
                return $bans;
            } else {
                return false;
            }
        }
    }

    public function GetPlayerBanList($id) {
        $id = (int) trim($id);

        $result = $this->BattlemetricsGET("https://api.battlemetrics.com/bans?filter[player]=$id&include=player,user");

        if(!$result) {
            return false;
        } else {
            return $result;
        }
    }

    private function BattleMetricsPOST($endpoint, $data) {
        if(is_array($data)) $data = json_encode($data);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer ".$this->bearer,
                "Content-Type: application/json",
                "Content-Length: ".strlen($data)
            ),
            CURLOPT_POSTFIELDS => $data
        ));

        $result = curl_exec($curl);
        curl_close($curl);
        $array = $this->JSONToArray($result);

        if(!$array) {
            return false;
        } else {
            if(isset($array["errors"])) return false;

            return $array;
        }
    }

    private function BattleMetricsGET($endpoint) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $endpoint,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer ".$this->bearer,
                "Content-Type: application/json"
            )
        ));

        $result = curl_exec($curl);
        curl_close($curl);
        $array = $this->JSONToArray($result);

        if(!$array) {
            return false;
        } else {
            if(isset($array["errors"])) return false;

            if(isset($array["links"]["next"])) {
                $current = $array;

                while(isset($current["links"]["next"])) {
                    $data = $this->BattleMetricsGET($current["links"]["next"]);

                    if(!$data) {
                        $current = null;
                    } else {
                        $current = $data;
                        $array = array_merge_recursive($array, $data);
                    }
                }
            }

            return $array;
        }
    }

    private function JSONToArray($a) {
        $array = json_decode($a, true);

        if(json_last_error() === JSON_ERROR_NONE) {
            return $array;
        } else {
            return false;
        }
    }

    private function GetBanningAdmin($data) {
        $admins = [];

        foreach($data as $item) {
            if($item["type"] === "user") {
                $admins[$item["attributes"]["id"]] = [];
                $admins[$item["attributes"]["id"]] = $item["attributes"]["nickname"];
            }
        }

        return $admins;
    }

    private function ReplaceBanDuration($reason, $timestamp, $expires) {
        $start = strtotime($timestamp);
        $end = strtotime($expires);

        $duration = round(($end - $start) / (60 * 60 * 24))." days";
        if(!$end) {
            $expires = "Perm";
            $duration = "Perm";
        } elseif($end <= time()) {
            $expires = "Expired";
        } else {
            $expires = "Expires: ".date("F j Y, g:i a", strtotime($expires));
        }
        $replace = array(
            "{{duration}}" => $duration,
            "Remaining: {{timeLeft}}" => $expires
        );

        return str_replace(array_keys($replace), $replace, $reason);
    }

}
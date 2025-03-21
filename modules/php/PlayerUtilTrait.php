<?php

namespace Bga\Games\Cardia;

trait PlayerUtilTrait {

    function getNonZombiePlayersIds() {
        $sql = "SELECT player_id FROM player WHERE player_eliminated = 0 AND player_zombie = 0 ORDER BY player_no";
        $dbResults = $this->getCollectionFromDB($sql);
        return array_map(fn($dbResult) => intval($dbResult['player_id']), array_values($dbResults));
    }

    /**
     *
     * @return integer player position (as player_no) from database
     */
    function getPlayerPosition($player_id) {
        $players = $this->loadPlayersBasicInfos();
        if (!isset($players[$player_id])) {
            return -1;
        }
        return $players[$player_id]['player_no'];
    }

    function getPlayersInOrder() {
        $result = array();

        $players = $this->loadPlayersBasicInfos();
        $next_player = $this->getNextPlayerTable();
        $player_id = $this->getCurrentPlayerId();

        // Check for spectator
        if (!key_exists($player_id, $players)) {
            $player_id = $next_player[0];
        }

        // Build array starting with current player
        for ($i = 0; $i < count($players); $i++) {
            $result[$player_id] = $players[$player_id];
            $player_id = $next_player[$player_id];
        }
        return $result;
    }

    function getPlayersIds() {
        return array_keys($this->loadPlayersBasicInfos());
    }

    function getOpponentId($currentPlayerId) {
        return $this->getFirstElementInArray(array_diff($this->getPlayersIds(), [$currentPlayerId]));
    }

    function getPlayerIdFromPosition($position) {
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_no'] == $position) {
                return $player_id;
            }
        }
        return null;
    }

    function getPlayers() {
        return $this->loadPlayersBasicInfos();
    }

    function getPlayerColor($playerId) {
        $sql = "SELECT player_id, player_color FROM player WHERE player_id = $playerId";
        return $this->getNonEmptyObjectFromDb($sql);
    }

    function getPlayerIdsInOrder($starting) {
        $player_ids = $this->getPlayersIds();
        $rotate_count = array_search($starting, $player_ids);
        if ($rotate_count === false) {
            return $player_ids;
        }
        for ($i = 0; $i < $rotate_count; $i++) {
            array_push($player_ids, array_shift($player_ids));
        }
        //var_dump("getPlayerIdsInOrder()",$player_ids); 
        return $player_ids;
    }

    function getPlayerCount() {
        return count($this->getPlayersIds());
    }

    function getPlayerIdByOrder($playerOrder = 1) {
        return $this->getUniqueIntValueFromDB("SELECT player_id FROM player where `player_no` = $playerOrder");
    }

    function getLastPlayer() {
        return $this->getPlayerIdByOrder($this->getPlayerCount());
    }

    function getPlayerName(int $playerId) {
        return $this->getUniqueValueFromDb("SELECT player_name FROM player WHERE player_id = $playerId");
    }

    function isLastPlayer(int $playerId) {
        return $this->getLastPlayer() == $playerId;
    }

    function getWinners() {
        $sql = "SELECT player_id FROM player WHERE player_score = (SELECT max(player_score) from player)";
        return $this->getObjectListFromDB($sql, true);
    }

    function getMaxScore() {
        $sql = "SELECT max(player_score) from player";
        return $this->getUniqueIntValueFromDB($sql);
    }

    function getPlayerScore(int $playerId) {
        return $this->getUniqueIntValueFromDB("SELECT player_score FROM player where `player_id` = $playerId");
    }

    function incPlayerScore(int $playerId, int $delta, $message = null, $messageArgs = []) {
        static::DbQuery("UPDATE player SET `player_score` = `player_score` + $delta where `player_id` = $playerId");

        $this->notifyAllPlayers('points', $message !== null ? $message : '', [
            'playerId' => $playerId,
            'player_name' => $this->getPlayerName($playerId),
            'points' => $this->getPlayerScore($playerId),
            'delta' => $delta,
        ] + $messageArgs);
    }

    function isEveryPlayerScoreEqualTo(int $score) {
        return $this->getUniqueValueFromDB("SELECT count(*) from player where player_score = $score") == $this->getPlayerCount();
    }
    
    function incPlayerField(int $playerId, String $field, int $delta) {
        static::DbQuery("UPDATE player SET `$field` = `$field` + $delta where `player_id` = $playerId");
    }

    function updatePlayer(int $playerId, String $field, int $newValue) {
        static::DbQuery("UPDATE player SET $field = $newValue WHERE player_id = $playerId");
    }

    function updatePlayersExceptOne(int $playerId, String $field, int $newValue) {
        static::DbQuery("UPDATE player SET $field = $newValue WHERE player_id != $playerId");
    }

    function getPlayerFieldValue(int $playerId, String $field) {
        return $this->getUniqueValueFromDB("select $field from player WHERE player_id = $playerId");
    }

    function getUniquePlayerIdByFieldValue(string $field, string $value) {
        return $this->getUniqueValueFromDB("select player_id from player WHERE $field = $value");
    }

    function getMostlyActivePlayerId() {
        $state = $this->gamestate->state();
        if ($state['type'] === "multipleactiveplayer") {
            return $this->getCurrentPlayerId();
        } else {
            return $this->getActivePlayerId();
        }
    }

    function getMostlyActivePlayerOrder() {
        return $this->getPlayerPosition($this->getMostlyActivePlayerId());
    }
}

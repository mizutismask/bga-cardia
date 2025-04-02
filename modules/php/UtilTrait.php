<?php

namespace Bga\Games\Cardia;

trait UtilTrait {

    function array_find(array $array, callable $fn) {
        foreach ($array as $value) {
            if ($fn($value)) {
                return $value;
            }
        }
        return null;
    }

    function array_find_index(array $array, callable $fn) {
        foreach ($array as $index => $value) {
            if ($fn($value)) {
                return $index;
            }
        }
        return null;
    }

    function array_some(array $array, callable $fn) {
        foreach ($array as $value) {
            if ($fn($value)) {
                return true;
            }
        }
        return false;
    }

    function array_every(array $array, callable $fn) {
        foreach ($array as $value) {
            if (!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    function getIds(array $cards) {
        $ids = [];
        foreach ($cards as $card) {
            $ids[] = $card->id;
        }
        return $ids;
    }

    function getFirstElementInArray($pArray) {
        return $pArray[array_key_first($pArray)] ?? null;
    }

    public function getStateName() {
        $state = $this->gamestate->state();
        return $state['name'];
    }

    public function getRandomKey(array &$array) {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomKey(): Array is empty", E_USER_WARNING);
            return null;
        }
        $rand = random_int(0, $size - 1);
        $slice = array_slice($array, $rand, 1, true);
        foreach ($slice as $key => $value) {
            return $key;
        }
    }

    public function getRandomValue(array &$array) {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomValue(): Array is empty", E_USER_WARNING);
            return null;
        }
        $rand = random_int(0, $size - 1);
        $slice = array_slice($array, $rand, 1, true);
        foreach ($slice as $key => $value) {
            return $value;
        }
    }

    public function getRandomSlice(array &$array, int $count) {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomSlice(): Array is empty", E_USER_WARNING);
            return null;
        }
        if (
            $count < 1 || $count > $size
        ) {
            trigger_error(
                "getRandomSlice(): Invalid count $count for array with size $size",
                E_USER_WARNING
            );
            return null;
        }
        $slice = [];
        $randUnique = [];
        while (count($randUnique) < $count) {
            $rand = random_int(0, $size - 1);
            if (array_key_exists($rand, $randUnique)) {
                continue;
            }
            $randUnique[$rand] = true;
            $slice += array_slice($array, $rand, 1, true);
        }
        return $slice;
    }

    /**
     * Auto initialize stats. Note for this to work your game stats ids have to be prefixed by game_ (verbatim)
     */
    public function initStats() {
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats['player'];
        // auto-initialize all stats that starts with game_
        // we need a prefix because there is some other system stuff
        foreach ($player_stats as $key => $value) {
            if ($this->startsWith($key, 'game_')) {
                $this->initStat('player', $key, 0);
            }
            if ($key === 'turns_number') {
                $this->initStat('player', $key, 0);
            }
        }
        $table_stats = $all_stats['table'];
        foreach ($table_stats as $key => $value) {
            if ($this->startsWith($key, 'game_')) {
                $this->initStat('table', $key, 0);
            }
            if ($key === 'turns_number') {
                $this->initStat('table', $key, 0);
            }
        }
    }

    function isStudio() {
        return ($this->getBgaEnvironment() == 'studio');
    }

    function debugConsole($info, $args = array()) {
        $this->notifyAllPlayers("log", '', ['log' => $info, 'args' => $args]);
        $this->warn($info);
    }

    function startsWith(string $haystack, string $needle): bool {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }

    function endsWith(string $haystack, string  $needle): bool {
        $length = strlen($needle);
        return $length === 0 || (substr($haystack, -$length) === $needle);
    }

    function getPart(string $haystack, int $i, bool $noException = false): string {
        $parts = explode('_', $haystack);
        $len = count($parts);
        if ($noException && $i >= $len)
            return "";
        if ($noException && $len + $i < 0)
            return "";

        return $parts[$i >= 0 ? $i : $len + $i];
    }

    function getPartsPrefix(string $haystack, int $i) {
        $parts = explode('_', $haystack);
        $len = count($parts);
        if ($i < 0) {
            $i = $len + $i;
        }
        if ($i <= 0)
            return '';
        for (; $i < $len; $i++) {
            unset($parts[$i]);
        }
        return implode('_', $parts);
    }

    function toJson($data, $options = JSON_PRETTY_PRINT) {
        $json_string = json_encode($data, $options);
        return $json_string;
    }

    function array_value_get($array, $field, $default = null) {
        if (array_key_exists($field, $array)) {
            return $array[$field];
        } else {
            return $default;
        }
    }
    function array_value_inc(&$array, $field, $inc = 1) {
        if (array_key_exists($field, $array)) {
            $array[$field] += $inc;
        } else {
            $array[$field] = $inc;
        }
    }

    function getColoredGameStateValue($gameStateValue, $color) {
        return $this->getGameStateValue($gameStateValue . "_" . strtoupper($this->getColorName($color)));
    }

    public function checkVersion(int $clientVersion): void {
        if ($clientVersion != intval($this->gamestate->table_globals[300])) {
            throw new \BgaVisibleSystemException(self::_("A new version of this game is now available. Please reload the page (F5)."));
        }
    }

    function arrayGroupBy(array $data, $extractKeyFunction) {
        $dataByKey = [];
        foreach (array_values($data) as $token) {
            $key = $extractKeyFunction($token);
            if (!isset($dataByKey[$key])) {
                $dataByKey[$key] = [];
            }
            $dataByKey[$key][] = $token;
        }
        return $dataByKey;
    }

    /**
     * This will throw an exception if condition is false.
     * The message should be translated and shown to the user.
     *
     * @param $message string
     *            user side error message, translation is needed, use $this->_() when passing string to it
     * @param $cond boolean condition of assert
     * @param $log string optional log message, not need to translate
     * @throws BgaUserException
     */
    function userAssertTrue($message, $cond = false, $log = "") {
        if ($cond)
            return;
        if ($log)
            $this->warn("$message $log|");
        throw new \BgaUserException($message);
    }

    /**
     * This will throw an exception if condition is false.
     * This only can happened if user hacks the game, client must prevent this
     *
     * @param $log string
     *            server side log message, no translation needed
     * @param $cond boolean condition of assert
     * @throws BgaUserException
     */
    function systemAssertTrue($log, $cond = false) {
        if ($cond)
            return;
        $move = $this->getGameStateValue(GS_PLAYER_TURN_NUMBER);
        $this->error("Internal Error during move $move: $log|");
        $e = new \Exception($log);
        $this->error($e->getTraceAsString());
        throw new \BgaUserException(self::_("Internal Error. That should not have happened. Please raise a bug."));
    }

    function notifyWithName($type, $message = '', $args = null, $player_id = -1) {
        if ($args == null)
            $args = array();
        $this->systemAssertTrue("Invalid notification signature", is_array($args));
        if (array_key_exists('playerId', $args) && $player_id == -1) {
            $player_id = $args['playerId'];
        }
        if ($player_id == -1)
            $player_id = $this->getMostlyActivePlayerId();
        if ($player_id != 'all')
            $args['player_id'] = $player_id;
        if ($message) {
            $player_name = $this->getPlayerName($player_id);
            $args['player_name'] = $player_name;
        }
        if (array_key_exists('_notifType', $args)) {
            $type = $args['_notifType'];
            unset($args['_notifType']);
        }
        if ($this->array_value_get($args, 'noa', false) || $this->array_value_get($args, 'nop', false) || $this->array_value_get($args, 'nod', false)) {
            $type .= "Async";
        }
        if (array_key_exists('_private', $args) && $args['_private']) {
            unset($args['_private']);
            $this->notifyPlayer($player_id, $type, $message, $args);
        } else {
            $this->notifyAllPlayers($type, $message, $args);
        }
    }

    function notifyCounterChange() {
        $this->notifyAllPlayers("updateCounters", "", array(
            'counters' => $this->argCounters(),
        ));
    }

    function findLongestSubarray($array) {
        return array_reduce($array, function ($longest, $subarray) {
            return count($subarray) > count($longest) ? $subarray : $longest;
        }, []);
    }

    function array_contains_card(array $array, string $cardId) {
        return $this->array_some($array, fn ($card) => $card->id == $cardId);
    }
}

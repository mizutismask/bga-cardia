<?php

namespace Bga\Games\cardia;

trait ContextTrait {

    function resolveLastContextIfAction($action) {
        $context = $this->dbGetLastContextToResolve();
        if ($context && $context["action"] == $action) {
            $this->dbResolveContextLog($context["id"]);
            //$this->dump('*******************Context just resolved', $context);
        } else {
            //$this->dump('*******************Last context not as expected', $action);
        }
    }

    function changeNextStateFromContext() {
        $nextState = "";
        $situation = $this->dbGetLastContextToResolve();
        //$this->dump('******************situation*', $situation);
        if (!$situation) {
            $nextState = "nextPlayer";
        } else {
            switch ($situation["action"]) {
                    /*case ACTION_PLAY_TICKET:
                    $nextState = "nextPlayer";
                    $this->dbResolveContextLog($situation["id"]);
                    break;
*/
                default:
                    # code...
                    break;
            }
        }

        $this->dump('******************nextState*', $nextState);
        $this->gamestate->nextState($nextState);
    }

    function dbInsertContextLog($action, $param1 = null, $param2 = null, $param3 = null) {
        $state = $this->gamestate->state()["name"];
        $player = $this->getMostlyActivePlayerId();
        $values[] = "( '$player', '$state', '$action', '$param1', '$param2', '$param3')";
        $sql = "INSERT INTO context_log (player, state, action, param1, param2, param3)";
        $sql .= " VALUES " . implode(",", $values);
        static::DbQuery($sql);
    }

    function dbResolveContextLog($contextId) {
        if (!$contextId) {
            $this->error("resolve context log can not be called with an undefined id");
        } else {
            $value = 1;
            $sql = "UPDATE context_log SET resolved = '$value' WHERE id = $contextId";
            static::DbQuery($sql);
        }
    }

    function dbGetLastContextToResolve($count = 1) {
        $positiveCount = $count <= 0 ? 1 : $count;
        $sql = "select * from context_log where resolved = 0 order by id desc limit $positiveCount";
        $res = $this->getObjectListFromDB($sql);
        if ($count == 1 && count($res)) return $res[0];
        return $res;
    }

    function dbGetLastResolvedContext() {
        $sql = "select * from context_log where resolved = 1 order by id desc limit 1";
        $res = $this->getObjectListFromDB($sql);
        if (count($res)) return $res[0];
        return $res;
    }
}

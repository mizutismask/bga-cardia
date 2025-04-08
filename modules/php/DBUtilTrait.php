<?php

namespace Bga\Games\Cardia;

trait DBUtilTrait {

    
    /**
     * Reorder cards in a specific card_location and card_type_arg from 1 to n.
     * 
     * @param string $cardLocation The card location to filter by.
     * @param int $cardTypeArg The card type argument to filter by.
     */
    function dbReorderCardsOfTypeArgInLocation($cardTypeArg, $cardLocation) {
        $sql = "
            UPDATE card AS c1
                JOIN (
                    SELECT card_id, (@rownum := @rownum + 1) AS new_location_arg
                    FROM (SELECT @rownum := 0) r, card
                    WHERE card_location = '$cardLocation'
                    AND card_type_arg = $cardTypeArg
                    ORDER BY card_location_arg ASC
                ) AS c2 ON c1.card_id = c2.card_id
                SET c1.card_location_arg = c2.new_location_arg;
                ";
        static::DbQuery($sql);
    }
    
    function dbEmptyTable($tableName) {
        static::DbQuery("delete from $tableName");
    }

    function getUniqueIntValueFromDB(string $sql) {
        return intval($this->getUniqueValueFromDB($sql));
    }

    function getUniqueBoolValueFromDB(string $sql) {
        return boolval($this->getUniqueValueFromDB($sql));
    }

    function dbIncField(String $table, String $field, int $value, String $pkfield, String $key) {
        static::DbQuery("UPDATE $table SET $field = $field+$value WHERE $pkfield = '$key'");
    }

    function getCardsFromLocationLike(string $tableName, string $likePattern) {
        $query = new QueryBuilder($tableName);
        return $query
            ->select($this->getTypicalTableFields())
            ->where('card_location', 'like', "$likePattern%")
            ->get();
    }

    function getTopOfLocationForTypeArg(string $tableName, string $location, int $typeArg) {
        $fields = [];
        foreach ($this->getTypicalTableFields() as $alias => $col) {
            $fields[] = is_numeric($alias) ? "`$col`" : "`$col` AS `$alias`";
        }
        $fields = implode(' , ', $fields);

        $query = "SELECT $fields FROM $tableName WHERE `card_location` = '$location' AND `card_type_arg` = $typeArg AND `card_location_arg` = (SELECT MAX(card_location_arg) FROM $tableName WHERE card_location = '$location' AND card_type_arg = $typeArg)";
        return $this->getObjectListFromDB($query)[0] ?? null;
    }
    
    function getBottomOfLocationForTypeArg(string $tableName, string $location, int $typeArg) {
        $fields = [];
        foreach ($this->getTypicalTableFields() as $alias => $col) {
            $fields[] = is_numeric($alias) ? "`$col`" : "`$col` AS `$alias`";
        }
        $fields = implode(' , ', $fields);

        $query = "SELECT $fields FROM $tableName WHERE `card_location` = '$location' AND `card_type_arg` = $typeArg AND `card_location_arg` = (SELECT MIN(card_location_arg) FROM $tableName WHERE card_location = '$location' AND card_type_arg = $typeArg)";
        return $this->getObjectListFromDB($query)[0] ?? null;
    }

    function getBottomIndexOfLocationForTypeArg(string $tableName, string $location, int $typeArg) {
        $fields = [];
        foreach (["card_location_arg"] as $alias => $col) {
            $fields[] = is_numeric($alias) ? "`$col`" : "`$col` AS `$alias`";
        }
        $fields = implode(' , ', $fields);

        $query = "SELECT $fields FROM $tableName WHERE `card_location` = '$location' AND `card_type_arg` = $typeArg AND `card_location_arg` = (SELECT MIN(card_location_arg) FROM $tableName WHERE card_location = '$location' AND card_type_arg = $typeArg)";
        return $this->getUniqueIntValueFromDB($query) ?? 0;
    }

    function getTypicalTableFields() {
        return [
            'id' => 'card_id',
            'type' => 'card_type',
            'type_arg' => 'card_type_arg',
            'location' => 'card_location',
            'location_arg' => 'card_location_arg'
        ];
    }

    function countCardsFromLocationLike(string $tableName, string $likePattern) {
        $sql = "SELECT count(card_id) FROM $tableName where card_location like '$likePattern%'";
        return $this->getUniqueIntValueFromDB($sql);
    }

    function dbArrayParam($arrayp) {
        return '"' . implode('","', $arrayp) . '"';
    }

    function refreshGlobalValue($global_id) {
        return $this->getUniqueValueFromDB("select global_value from global where global_id='$global_id'");
    }
}

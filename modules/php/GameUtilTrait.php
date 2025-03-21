<?php

namespace Bga\Games\Cardia;

trait GameUtilTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////
    /*function getColorName(int $color) {
        switch ($color) {
            case BLUE:
                return clienttranslate("blue");
            case YELLOW:
                return clienttranslate("yellow");
            case RED:
                return clienttranslate("red");
        }
    }*/

    /**
     * Transforms a Destination Db object to Destination class.
     */
    /* function getDestinationFromDb($dbObject) {
        if (!$dbObject || !array_key_exists('id', $dbObject)) {
            throw new BgaSystemException("Destination doesn't exists " . json_encode($dbObject));
        }

        //$this->dump('************type_arg*******', $dbObject["type_arg"]);
        //$this->dump('*******************', $this->DESTINATIONS[$dbObject["type"]][$dbObject["type_arg"]]);
        return new Destination($dbObject, [$this->DESTINATIONS]);
    }*/

    /**
     * Transforms a Destination Db object array to Destination class array.
     */
    function getDestinationsFromDb(array $dbObjects) {
        return array_map(fn($dbObject) => $this->getDestinationFromDb($dbObject), array_values($dbObjects));
    }

    /**
     * Transforms a ClaimedRoute json decoded object to ClaimedRoute class.
     */
    /* function getClaimedRouteFromGlobal($dbObject) {
        //$this->dump('*******************getClaimedRouteFromGlobal', $dbObject);
        if (
            $dbObject === null
        ) {
            return null;
        }
        if (!$dbObject) {
            throw new BgaSystemException("Claimed route doesn't exists " . json_encode($dbObject));
        }

        $class = new ClaimedRoute([]);
        foreach ($dbObject as $key => $value) $class->{$key} = $value;
        return $class;
    }*/
}

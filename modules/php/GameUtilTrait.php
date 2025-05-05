<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;

trait GameUtilTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////
    function getColorName(Faction $color) {
        switch ($color) {
            case Faction::GREEN:
                return clienttranslate("green");
            case Faction::YELLOW:
                return clienttranslate("yellow");
            case Faction::BLUE:
                return clienttranslate("blue");
            case Faction::RED:
                return clienttranslate("red");
        }
    }

    /**
     * Transforms a card Db object to Card class.
     */
    function getCardiaCardFromDb($dbObject) {
        if (!$dbObject || !array_key_exists('id', $dbObject)) {
            throw new \BgaSystemException("Card doesn't exists " . json_encode($dbObject));
        }

        //$this->dump('************type_arg*******', $dbObject["type_arg"]);
        //$this->dump('*******************', $this->DESTINATIONS[$dbObject["type"]][$dbObject["type_arg"]]);
        return new CardiaCard($dbObject, ["material" => $this->CARDIA_CARDS, "deck" => $this->refreshGlobalValue(101)]);
    }

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

<?php

namespace Bga\Games\Cardia\objects;

/**
 * A Wizard is a physical card. It contains informations from matching WizardCard, with technical informations like id and location.
 * Location : deck or hand
 * Location arg : order (in deck), playerId (in hand)
 * Type : the Wizard type
 * Type arg :  player color
 */
class CardiaCard extends CardiaCardInfo {
    public int $id;
    public string $location;
    public int $location_arg;
    public int $type;
    public int $type_arg;

    public function __construct($dbCard, array $additionalParameters) {
        array_key_exists('id', $dbCard) ? $this->id = intval($dbCard['id']) : null;
        array_key_exists('location', $dbCard) ? $this->location = $dbCard['location'] : null;
        array_key_exists('location_arg', $dbCard) ? $this->location_arg = intval($dbCard['location_arg']) : null;
        array_key_exists('type', $dbCard) ? $this->type = intval($dbCard['type']) : null;
        array_key_exists('type_arg', $dbCard) ? $this->type_arg = intval($dbCard['type_arg']) : null;
        if ($additionalParameters) {
            $materialInfo = $additionalParameters["material"];
            $cardInfo = $materialInfo[$this->type];
            $this->value = $cardInfo->value;
            $this->powerType = $cardInfo->powerType;
            $this->name = $cardInfo->name;
            $this->faction = $cardInfo->faction;
        }
    }

    public static function stripSecretInfo($card): CardiaCard {
        $copy = clone $card;
        $copy->type = 0;
        unset($copy->value);
        unset($copy->powerType);
        unset($copy->name);
        unset($copy->faction);
        return $copy;
    }
}

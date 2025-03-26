<?php

namespace Bga\Games\Cardia\objects;

use Bga\Games\Cardia\objects\PowerType;

/**
 * A CardiaCardInfo is the graphic representation of a card (informations on it : power, value, description…).
 */
class CardiaCardInfo {
    public int $value;
    public int $modifiedValue;
    public PowerType $powerType;
    public Faction $faction;
    public string $name; //translated

    public function __construct(int $value, PowerType $powerType, Faction $faction, string $name) {
        $this->value = $value;
        $this->powerType = $powerType;
        $this->name = $name;
        $this->faction = $faction;
    }
}

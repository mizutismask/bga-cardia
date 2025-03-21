<?php
namespace Bga\Games\cardia\objects;

/**
 * A WizardCardInfo is the graphic representation of a card (informations on it : power, value, description…).
 */
class WizardCardInfo {
    public int $value;
    public string $power;
    public string $name; //translated

    public function __construct(int $value, string $power, string $name) {
        $this->value = $value;
        $this->power = $power;
        $this->name = $name;
    }
}

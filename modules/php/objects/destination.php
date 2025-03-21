<?php

/**
 * A DestinationCard is the graphic representation of a card (informations on it : to, description).
 */
class DestinationCard {
    public int $to;
  
    public function __construct(int $to) {
        $this->to = $to;
    } 
}

/**
 * A Destination is a physical card. It contains informations from matching DestinationCard, with technical informations like id and location.
 * Location : deck or hand
 * Location arg : order (in deck), playerId (in hand)
 * Type : 1 for simple destination
 * Type arg : the destination type (DestinationCard id)
 */
class Destination extends DestinationCard {
    public int $id;
    public string $location;
    public int $location_arg;
    public int $type;
    public int $type_arg;

    public function __construct($dbCard, array $additionalParameters) {
        array_key_exists('id', $dbCard) ? $this->id = intval($dbCard['id']):null;
        array_key_exists('location', $dbCard) ? $this->location = $dbCard['location']:null;
        array_key_exists('location_arg', $dbCard) ? $this->location_arg = intval($dbCard['location_arg']):null;
        array_key_exists('type', $dbCard) ? $this->type = intval($dbCard['type']):null;
        array_key_exists('type_arg', $dbCard) ? $this->type_arg = intval($dbCard['type_arg']):null;
        $DESTINATIONS = $additionalParameters[0];
        $destinationCard = $DESTINATIONS[$this->type][$this->type_arg];
        $this->to = $destinationCard->to;
    } 
}
?>
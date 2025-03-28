<?php

namespace Bga\Games\Cardia;

/**
 * @property CardManager cardManager
 */
trait ArgsTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*function argChooseDuelCard() {
        $playerId = intval($this->getActivePlayerId());

        $destinations = $this->getPickedDestinationCards($playerId);

        return [
            'minimum' => 3,
            '_private' => [          // Using "_private" keyword, all data inside this array will be made private
                'active' => [       // Using "active" keyword inside "_private", you select active player(s)
                    'destinations' => $destinations,   // will be send only to active player(s)
                ]
            ],
        ];
    }*/


    function argChooseAction() {
        $playerId = intval($this->getActivePlayerId());

        $canPass = true;
        return [
            'canPass' => $canPass,
            'canResetTurn' => $this->globals->get(CAN_RESET_TURN),
        ];
    }

    function argCounters() {
        $players = $this->loadPlayersBasicInfos();
        $counters = array();
        foreach ($players as $playerId => $player) {

            $playerOrder = intval($player['player_no']);
            $name = "hand-cards-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' =>  $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $playerOrder, MATERIAL_LOCATION_HAND));

            $name = "discard-cards-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $playerOrder, MATERIAL_LOCATION_DISCARD));

            $name = "deck-cards-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $playerOrder, MATERIAL_LOCATION_DECK));

            $name = "signets-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' => $this->tokenManager->getSigilCount($playerOrder));
        }
        $this->dump('*******************counters', $counters);
        return $counters;
    }
}

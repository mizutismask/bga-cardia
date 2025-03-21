<?php

namespace Bga\Games\Cardia;

/**
 * @property CardManager cardManager
 */
trait ArgsTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */
    /*function argChooseAdditionalDestinations() {
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
    }
*/

    function argChooseAction() {
        $playerId = intval($this->getActivePlayerId());

        $canPass = true;
        return [
            'canPass' => $canPass,
            'canResetTurn' => $this->globals->get(CAN_RESET_TURN),
        ];
    }

    function argCounters() {
        $players = $this->getObjectListFromDB("SELECT player_id id FROM player", true);
        $counters = array();
        /*for ($i = 0; $i < count($players); $i++) {
            $name = "hand-cards-counter-$players[$i]";
            $counters[$name] = array('counter_name' => $name, 'counter_value' => $this->cardManager->getPlayerHandCount($players[$i]));
        }*/
        /* $cards_in_hand = $this->dessertcards->countCardsByLocationArgs(DECK_LOC_HAND);
        foreach ($cards_in_hand as $player_id => $cards_nbr) {
            $counters['cards_count_' . $player_id]['counter_value'] = $cards_nbr;
        }

        $won_cards = $this->countWonCardsByPlayerAndColor();
        foreach ($won_cards as $player_id => $cards_nbr_by_color) {
            foreach ($cards_nbr_by_color as $color => $count) {
                $counters['won_cards_count_' . $player_id . "_" . $color]['counter_value'] = $count;
            }
        }

        $counters['guest_draw_count'] = array('counter_name' => 'guest_draw_count', 'counter_value' => $this->guestcards->countCardInLocation(DECK_LOC_DECK));*/
        return $counters;
    }
}

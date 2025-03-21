<?php

namespace Bga\Games\cardia;

const TABLE_ACTION_CARD = "action_card";

class CardManager extends DeckManager {


    public function pickInitialActionCards() {
        $this->initRiver(4);
    }

    public function moveActionCardToPlayerHand($cardId, $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }
}

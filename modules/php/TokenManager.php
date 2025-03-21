<?php

namespace Bga\Games\Cardia;

const TABLE_TOKEN = "token";

class TokenManager extends DeckManager {


    public function pickInitialActionCards() {
        $this->initRiver(4);
    }

    public function moveActionCardToPlayerHand($cardId, $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }
}

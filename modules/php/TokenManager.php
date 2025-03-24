<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\TokenType;

const TABLE_TOKEN = "token";

class TokenManager extends DeckManager {

    public function getSigilCount(int $playerOrder) {
        $tokenType = TokenType::SIGIL->value;
        $tokenTable = TABLE_TOKEN;
        $cardTable = TABLE_CARD;

        $sql = "SELECT count(token.card_id) 
            FROM $tokenTable as token 
            JOIN $cardTable as card ON card.card_id = token.card_location_arg
            WHERE token.card_type = '$tokenType' 
            AND token.card_location = 'card' 
            AND card.card_type_arg = '$playerOrder'
        ";
        return intval($this->deck->getUniqueValueFromDB($sql));
    }
    public function pickInitialActionCards() {
        $this->initRiver(4);
    }

    public function moveActionCardToPlayerHand($cardId, $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }
}

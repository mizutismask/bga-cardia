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

    public function addSigilOnCard(int $cardId) {
        $sigils = $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_DECK));
        if(!$sigils) {
            throw new \BgaUserException(self::_("No more sigils"));
        }
        $sigil = reset($sigils);
        $this->deck->moveCard($sigil->id, MATERIAL_LOCATION_CARD, $cardId);
        $this->game->notifyWithName("materialMove",  "", [
            'type' => MATERIAL_TYPE_TOKEN,
            'from' => MATERIAL_LOCATION_DECK,
            'to' => MATERIAL_LOCATION_CARD,
            'toArg' => $cardId,
            'material' => [$this->getCard($sigil->id)],
        ]);
    }

    public function pickInitialActionCards() {
        $this->initRiver(4);
    }

    public function moveActionCardToPlayerHand($cardId, $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }
}

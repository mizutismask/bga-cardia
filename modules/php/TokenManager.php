<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\TokenType;

const TABLE_TOKEN = "token";

class TokenManager extends DeckManager {

    public function getSignetCount(int $playerOrder) {
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

    public function getSignetsOnCards(){
        return $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_CARD));
    }

    public function addSignetOnCard(int $cardId) {
        $signets = $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_DECK));
        if (!$signets) {
            throw new \BgaUserException(self::_("No more signets"));
        }
        $signet = reset($signets);
        $this->deck->moveCard($signet->id, MATERIAL_LOCATION_CARD, $cardId);
        $this->game->notifyWithName("materialMove",  "", [
            'type' => MATERIAL_TYPE_TOKEN,
            'from' => MATERIAL_LOCATION_DECK,
            'to' => MATERIAL_LOCATION_CARD,
            'toArg' => $cardId,
            'material' => [$this->getCard($signet->id)],
        ]);
    }

    public function discardTokenOfTypeOnCard(CardiaCard $card, TokenType $tokenType) {
        $tokens = $this->deck->getCardsOfTypeInLocation($tokenType->value, $card->id, MATERIAL_LOCATION_CARD);
        foreach($tokens as $token){
            $this->deck->moveCard($token->id, MATERIAL_LOCATION_DISCARD);
            $this->game->notifyWithName("materialMove", "", [
                'type' => MATERIAL_TYPE_TOKEN,
                'from' => MATERIAL_LOCATION_CARD,
                'fromArg' => $card->id,
                'to' => MATERIAL_LOCATION_DISCARD,
                'material' => [$this->getCard($token->id)],
            ]);
        }
    }

    public function discardTokensOnDuelCard(CardiaCard $card){
        $this->discardTokenOfTypeOnCard($card, TokenType::SIGIL);
        $this->discardTokenOfTypeOnCard($card, TokenType::ONGOING);
    }

    public function pickInitialActionCards() {
        $this->initRiver(4);
    }

    public function moveActionCardToPlayerHand($cardId, $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }
}

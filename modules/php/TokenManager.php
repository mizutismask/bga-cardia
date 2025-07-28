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

        $sql = str_replace(array("\r", "\n"), ' ', "SELECT count(token.card_id) 
                    FROM $tokenTable as token 
                    JOIN $cardTable as card ON card.card_id = token.card_location_arg
                    WHERE token.card_type = '$tokenType' 
                    AND token.card_location = 'card' 
                    AND card.card_type_arg = '$playerOrder'
                ");
        return intval($this->deck->getUniqueValueFromDB($sql));
    }

    public function getSignetsOnPlayerCards(int $playerOrder) {
        $tokenType = TokenType::SIGIL->value;
        $tokenTable = TABLE_TOKEN;
        $cardTable = TABLE_CARD;
        $fields = [];

        $typicalFields = [
            'id' => 'token.card_id',
            'type' => 'token.card_type',
            'type_arg' => 'token.card_type_arg',
            'location' => 'token.card_location',
            'location_arg' => 'token.card_location_arg'
        ];
        foreach ($typicalFields as $alias => $col) {
            $fields[] = "$col AS `$alias`";
        }
        $fields = implode(' , ', $fields);

        $sql = str_replace(array("\r", "\n"), ' ', "SELECT $fields 
                    FROM $tokenTable as token 
                    JOIN $cardTable as card ON card.card_id = token.card_location_arg
                    WHERE token.card_type = '$tokenType' 
                    AND token.card_location = 'card' 
                    AND card.card_type_arg = '$playerOrder'
                ");
        return $this->cast($this->game->getObjectListFromDB($sql));
    }

    public function getSignetsOnCards() {
        return $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_CARD));
    }

    public function getOngoingTokensOnCards() {
        return $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::ONGOING->value, null, MATERIAL_LOCATION_CARD));
    }

    public function hasOngoingToken(int $cardId) {
        return !empty($this->deck->getCardsOfTypeInLocation(TokenType::ONGOING->value, null, MATERIAL_LOCATION_CARD, $cardId));
    }

    public function getOngoingTokenOnCards(int $cardId) {
        return $this->deck->getCardsOfTypeInLocation(TokenType::ONGOING->value, null, MATERIAL_LOCATION_CARD, $cardId);
    }

    public function addSignetOnCard(CardiaCard $card, ?CardiaCard $opposingCard, ?bool $severalPossible = false): bool {
        $winnerChanged = false;
        //$this->game->dump('*******************$this->alreadyHasSignet($cardId)', $this->alreadyHasSignet($cardId));
        if ($severalPossible || !$this->hasSignet($card->id)) {
            $signet = $this->getSignetToUse($opposingCard->id ?? null);
            $winnerChanged = $signet->location == MATERIAL_LOCATION_CARD;
            $this->deck->moveCard($signet->id, MATERIAL_LOCATION_CARD, $card->id);
            $this->game->notifyWithName("materialMove",  "", [
                'type' => MATERIAL_TYPE_TOKEN,
                'from' => $signet->location,
                'fromArg' => $signet->location_arg,
                'to' => MATERIAL_LOCATION_CARD,
                'toArg' => $card->id,
                'material' => [$this->getCard($signet->id)],
            ]);
            $this->game->notifyCounterChange();
        }
        return $winnerChanged;
    }

    public function hasSignet(int $cardId) {
        return !empty($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_CARD, $cardId));
    }

    public function getSignetToUse(?int $opposingCardId) {
        $signet = null;
        if ($opposingCardId) {
            //look for a signet on the opposing card
            $signets = $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_CARD, $opposingCardId));
            if ($signets) {
                $signet = reset($signets);
            }
        }
        //if no signet on opposing card, look for a signet from the deck
        if (!$signet) {
            $signets = $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_DECK));
            if (!$signets) {
                throw new \BgaUserException(_("No more signets"));
            }
            $signet = reset($signets);
        }
        return $signet;
    }

    public function getSignetsOnCard(int $cardId) {
        $signets = $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::SIGIL->value, null, MATERIAL_LOCATION_CARD, $cardId));
        return $signets;
    }

    public function addOngoingTokenOnCard(int $cardId) {
        $tokens = $this->cast($this->deck->getCardsOfTypeInLocation(TokenType::ONGOING->value, null, MATERIAL_LOCATION_DECK));
        if (!$tokens) {
            throw new \BgaUserException(_("No more ongoing tokens"));
        }
        $token = reset($tokens);
        $this->deck->moveCard($token->id, MATERIAL_LOCATION_CARD, $cardId);
        $this->game->notifyWithName("materialMove",  "", [
            'type' => MATERIAL_TYPE_ONGOING_TOKEN,
            'from' => MATERIAL_LOCATION_DECK,
            'to' => MATERIAL_LOCATION_CARD,
            'toArg' => $cardId,
            'material' => [$this->getCard($token->id)],
        ]);
    }

    public function discardTokenOfTypeOnCard(CardiaCard $card, TokenType $tokenType, $onlyOne = false): int {
        $tokens = $this->cast($this->deck->getCardsOfTypeInLocation($tokenType->value, null, MATERIAL_LOCATION_CARD,  $card->id));
        $count = 0;
        foreach ($tokens as $token) {
            if (!$onlyOne || $count == 0) {
                $this->deck->moveCard($token->id, MATERIAL_LOCATION_DECK);
                $this->game->notifyWithName("materialMove", "", [
                    'type' => $tokenType == TokenType::ONGOING ? MATERIAL_TYPE_ONGOING_TOKEN : MATERIAL_TYPE_TOKEN,
                    'from' => MATERIAL_LOCATION_CARD,
                    'fromArg' => $card->id,
                    'to' => MATERIAL_LOCATION_DECK,
                    'material' => [$this->getCard($token->id)],
                ]);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Returns the number of ongoing tokens discarded from the card.
     */
    public function discardTokensOnDuelCard(CardiaCard $card):int {
        $this->discardTokenOfTypeOnCard($card, TokenType::SIGIL);
        return $this->discardTokenOfTypeOnCard($card, TokenType::ONGOING);
    }

    public function pickInitialActionCards() {
        $this->initRiver(4);
    }

    public function moveActionCardToPlayerHand($cardId, $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }

    public function resetTokens() {
        $this->deck->moveAllCardsInLocation("card", MATERIAL_LOCATION_DECK);
    }
}

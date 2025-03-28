<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;

const TABLE_CARD = "card";

class CardManager extends DeckManager {

    public function dealHands($notify = false) {
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $cards = array_slice($this->getCardsOfTypeArgFromLocationOrderBy(TABLE_CARD, $player['player_no'], 'deck', "card_location_arg", true), 0, 5);
            $this->deck->moveCards(array_map(fn($c) => $c->id, $cards), "hand", $playerId);

            if ($notify) {
                $this->game->notifyPlayer($playerId, "materialMove",  "", [
                    'playerId' => $playerId,
                    'type' => $this->materialType,
                    'from' => MATERIAL_LOCATION_DECK,
                    'to' => MATERIAL_LOCATION_HAND,
                    'toArg' => $playerId,
                    'material' => $this->cast($this->deck->getCards(array_map(fn($c) => $c->id, $cards))),
                ]);
            }
        }
    }

    public function moveCardToLocation(CardiaCard $card, string $location, int $locationArg, $notify=true, $playerId=null) {
        $this->deck->moveCard($card->id, $location, $locationArg);

        if ($notify && $playerId) {
            $this->game->notifyPlayer($playerId, "materialMove",  "", [
                'playerId' => $playerId,
                'type' => $this->materialType,
                'from' => $card->location,
                'fromArg' => $card->location_arg,
                'to' => $location,
                'toArg' => $locationArg,
                'material' => [$this->castSingle($this->deck->getCard($card->id))],
            ]);
        }
        $this->game->notifyCounterChange();
    }

    public function pickAdditionalCard() {
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $cards = $this->getCardsOfTypeArgFromLocationOrderBy(TABLE_CARD, $player['player_no'], 'deck', "card_location_arg", true);
            if ($cards) {
                $c = $cards[0];
                $this->deck->moveCard($c->id, "hand", $playerId);

                $this->game->notifyPlayer($playerId, "materialMove",  "", [
                    'playerId' => $playerId,
                    'type' => $this->materialType,
                    'from' => MATERIAL_LOCATION_DECK,
                    'to' => MATERIAL_LOCATION_HAND,
                    'toArg' => $playerId,
                    'material' => $this->cast([($this->deck->getCard($c->id))]),
                ]);
            }
        }
        $this->game->notifyCounterChange();
    }

    public function discardDuelCard(CardiaCard $card) {
        $this->deck->moveCard($card->id, MATERIAL_LOCATION_DISCARD);
        $this->updateCardModifier($card, 0);

        $playerId = $this->game->getPlayerIdFromPosition($card->type_arg);
        $this->game->notifyWithName("materialMove", "", [
            'playerId' => $playerId,
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_ENCOUNTER,
            'fromArg' => $card->location_arg,
            'to' => MATERIAL_LOCATION_DISCARD,
            'toArg' => $playerId,
            'material' => $this->cast([($this->deck->getCard($card->id))]),
            'cardName' => $card->name,
            'i18n' => ['cardName'],
        ]);
        $this->game->notifyCounterChange();
    }

    public function updateCardModifier(CardiaCard $card, int $modifier) {
        $query = new QueryBuilder(TABLE_CARD);
        return $query
            ->update(["modifiers" => $modifier], $card->id)
            ->execute();
    }

    public function playCard(CardiaCard $card, int $playerId, int $duelCount) {
        $this->deck->moveCard($card->id, MATERIAL_LOCATION_ENCOUNTER, $duelCount);
        $this->game->notifyWithName("materialMove",  clienttranslate('${player_name} plays ${cardName}'), [
            'playerId' => $playerId,
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_HAND,
            'to' => MATERIAL_LOCATION_ENCOUNTER,
            'toArg' => $duelCount,
            'material' => $this->cast([($this->deck->getCard($card->id))]),
            'cardName' => $card->name,
            'i18n' => ['cardName'],
        ]);
        $this->game->notifyCounterChange();
    }

    function getDuelsList() {
        $cards = $this->getCardsInLocation(MATERIAL_LOCATION_ENCOUNTER);
        $duels = [];
        foreach ($cards as $card) {
            $duels[$card->location_arg][$this->game->getPlayerIdFromPosition($card->type_arg)] = $card;
        }
        return $duels;
    }

    function getOpposingCard(CardiaCard $card, array $duels) {
        $opposingCard = null;
        //look for the duel containing the card
        foreach ($duels as $num => $duel) {
            foreach ($duel as $playerId => $duelCard) {
                if ($duelCard->id == $card->id) {
                    $opposingCard = $duel[array_diff(array_keys($duel), [$playerId])];
                    break;
                }
            }
        }
        return $opposingCard;
    }

    public function getModifierValueOnCard(int $cardId): int {
        return $this->game->getUniqueIntValueFromDB("SELECT card_modifier FROM card WHERE card_id = $cardId");
    }

    public function resetDecks() {
        $this->deck->moveAllCardsInLocation("discard", "deck");
        $this->deck->moveAllCardsInLocation("hand", "deck");
        $this->deck->shuffle("deck");
        $this->dealHands(true);
        $this->game->notifyCounterChange();
    }
}

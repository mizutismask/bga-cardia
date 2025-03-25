<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;

const TABLE_CARD = "card";

class CardManager extends DeckManager {


    public function dealHands($notify= false) {
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $cards = array_slice($this->getCardsOfTypeArgFromLocationOrderBy(TABLE_CARD, $player['player_no'], 'deck', "card_location_arg", true), 0, 5);
            $this->deck->moveCards(array_map(fn($c) => $c->id, $cards), "hand", $playerId);

            if($notify){
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
    }

    public function playCard(CardiaCard $card, $playerId, $duelCount) {
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
    }

    public function getModifierValueOnCard(int $cardId): int {
        return $this->game->getUniqueIntValueFromDB("SELECT card_modifier FROM card WHERE card_id = $cardId");
    }

    public function resetDecks() {
        $this->deck->moveAllCardsInLocation("discard", "deck");
        $this->deck->moveAllCardsInLocation("hand", "deck");
        $this->deck->shuffle("deck");
        $this->dealHands(true);
    }
}

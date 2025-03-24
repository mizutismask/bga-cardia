<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;

const TABLE_CARD = "card";

class CardManager extends DeckManager {


    public function pickInitialCards() {
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $cards = array_slice($this->getCardsOfTypeArgFromLocationOrderBy(TABLE_CARD, $player['player_no'], 'deck', "card_location_arg", true), 0, 5);
            $this->deck->moveCards(array_map(fn($c) => $c->id, $cards), "hand", $playerId);
        }
    }

    /*public function moveActionCardToPlayerHand($cardId, $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }*/
}

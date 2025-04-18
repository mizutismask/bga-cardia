<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;

const TABLE_CARD = "card";

class CardManager extends DeckManager {

    public function dealHands($notify = false) {
        $qty = 5;
        if ($this->game->getScenery() == GRAND_LIBRARY) {
            $qty = 2;
        }
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $this->addCardsToHand($qty, $playerId, $player["player_no"], $notify);
        }
    }

    public function addCardsToHand(int $qty, $playerId, int $playerPosition, $notify = false) {
        $cards = array_slice($this->getCardsOfTypeArgFromLocationOrderBy(TABLE_CARD, $playerPosition, 'deck', "card_location_arg", true), 0, $qty);
        $this->deck->moveCards(array_map(fn($c) => $c->id, $cards), "hand", $playerId);
        if ($notify) {
            $this->game->notifyPlayer($playerId, "materialMove",  "", [
                'playerId' => $playerId,
                'type' => $this->materialType,
                'from' => MATERIAL_LOCATION_DECK,
                'fromArg' =>  $playerId,
                'to' => MATERIAL_LOCATION_HAND,
                'toArg' => $playerId,
                'material' => $this->cast($this->deck->getCards(array_map(fn($c) => $c->id, $cards))),
            ]);
        }
    }

    public function replenishHands() {
        if (isset($this->castParameters["location"]) && $this->castParameters["location"] == BAZAAR) {
            $players = $this->game->loadPlayersBasicInfos();
            foreach ($players as $playerId => $player) {
                $cardsCount = count($this->getCardsOfTypeArgFromLocation(TABLE_CARD, $player["player_no"], MATERIAL_LOCATION_HAND));
                if ($cardsCount <= 1) {
                    $this->addCardsToHand(4, $playerId, $player["player_no"], true);
                }
            }
            $this->game->notifyCounterChange();
        }
    }

    public function moveCardToLocation(CardiaCard $card, string $location, int $locationArg, $notify = true, $playerId = null) {
        $this->deck->moveCard($card->id, $location, $locationArg);

        if ($notify && $playerId) {
            $this->game->notifyAllPlayers("materialMove",  "", [
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

    public function reorderDuels($encounter) {
        $this->game->dump('*******************reorderDuels', $encounter);
        $query = new QueryBuilder(TABLE_CARD);
        $query->where("card_location", "=", MATERIAL_LOCATION_ENCOUNTER)
            ->where("card_location_arg", ">", $encounter)
            ->inc(["card_location_arg" => -1])->run();

        $query = new QueryBuilder(TABLE_CARD);
        $moved = $query->select($this->game->getTypicalTableFields())->where("card_location", "=", MATERIAL_LOCATION_ENCOUNTER)->where("card_location_arg", ">=", $encounter)->get();
        $this->game->notifyAllPlayers("materialMove",  "", [
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_ENCOUNTER,
            'to' => MATERIAL_LOCATION_ENCOUNTER,
            'material' => $this->cast($moved),
        ]);
        $this->game->globals->inc(GLB_DUEL_COUNT, -1);
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

    public function incCardModifier(CardiaCard $card, int $modifier): void {
        $query = new QueryBuilder(TABLE_CARD);
        $query->inc(["card_modifier" => $modifier], $card->id);
        $this->game->notifyAllPlayers("updateModifiers", "", array(
            'modifiers' => $this->getModifiers(),
        ));
    }

    public function updateCardModifier(CardiaCard $card, int $modifier): void {
        $query = new QueryBuilder(TABLE_CARD);
        $query->update(["card_modifier" => $modifier], $card->id);
        $this->game->notifyAllPlayers("updateModifiers", "", array(
            'modifiers' => $this->getModifiers(),
        ));
    }

    public function playCard(CardiaCard $card, int $playerId, int $duelCount): object|null {
        $this->deck->moveCard($card->id, MATERIAL_LOCATION_ENCOUNTER, $duelCount);
        $refreshedCard = $this->castSingle($this->deck->getCard($card->id));
        $this->game->notifyWithName("materialMove",  clienttranslate('${player_name} plays ${cardName}'), [
            'playerId' => $playerId,
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_HAND,
            'to' => MATERIAL_LOCATION_ENCOUNTER,
            'toArg' => $duelCount,
            'material' => [$refreshedCard],
            'cardName' => $card->name,
            'i18n' => ['cardName'],
        ]);
        $this->game->notifyCounterChange();
        return $refreshedCard;
    }

    function getDuelsList() {
        $cards = $this->getCardsInLocation(MATERIAL_LOCATION_ENCOUNTER);
        $duels = [];
        foreach ($cards as $card) {
            $duels[$card->location_arg][$this->game->getPlayerIdFromPosition($card->type_arg)] = $card;
        }
        return $duels;
    }

    function getCardInPlay($cardType, $playerId): CardiaCard|null {
        $query = new QueryBuilder(TABLE_CARD);
        $cards = $query->select($this->game->getTypicalTableFields())
            ->where("card_location", "=", MATERIAL_LOCATION_ENCOUNTER)
            ->where("card_type", "=", $cardType)
            ->where("card_type_arg", "=", $this->game->getPlayerPosition($playerId))
            ->get();

        return $this->castSingle(reset($cards), true);
    }

    function discardTopOfDeck(int $playerId, int $playerPosition) {
        $top = $this->getCastedTopOfLocationForTypeArg(MATERIAL_LOCATION_DECK, $playerPosition);
        if ($top) {
            $this->discardCard($playerId, $top->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $top->name]);
        } else {
            $this->game->notifyWithName("msg",  clienttranslate('${player_name} has no card in deck to discard'), [
                'playerId' => $playerId,
            ]);
        }
    }

    function moveCardToBottomOfDeck(CardiaCard $card, $playerId) {
        $index = $this->game->getBottomIndexOfLocationForTypeArg(TABLE_CARD, MATERIAL_LOCATION_DECK, $card->type_arg);
        $index--;
        $this->moveCardToLocation($card, MATERIAL_LOCATION_DECK, $index, true, $playerId);
    }

    function getModifiers() {
        $modifiers = [];
        $query = new QueryBuilder(TABLE_CARD);
        $cards = $query
            ->select(["card_id", "card_modifier"])
            //->where('card_modifier', '!=', 0)
            ->get();
        foreach ($cards as $card) {
            $modifiers[$card["card_id"]] = intval($card["card_modifier"]);
        }
        return $modifiers;
    }

    function getOpposingCard(CardiaCard $card, array $duels) {
        $opposingCard = null;
        //look for the duel containing the card
        foreach ($duels as $num => $duel) {
            foreach ($duel as $playerId => $duelCard) {
                if ($duelCard->id == $card->id) {
                    $opponents = array_diff(array_keys($duel), [$playerId]);
                    $opposingCard = $duel[reset($opponents)];
                    break;
                }
            }
        }
        return $opposingCard;
    }

    public function getModifierValueOnCard(int $cardId): int {
        return $this->game->getUniqueIntValueFromDB("SELECT card_modifier FROM card WHERE card_id = $cardId");
    }

    public function getFactionCardsInHand(int $playerId, Faction $faction) {
        $cards = $this->getCardsOfTypeArgFromLocation(TABLE_CARD, $this->game->getPlayerPosition($playerId), MATERIAL_LOCATION_HAND);
        $factionCards = array_values(array_filter($cards, fn($card) => $card->faction == $faction));
        return $factionCards;
    }

    public function getFactionCardsInDeck(int $playerId, Faction $faction) {
        $cards = $this->getCardsOfTypeArgFromLocation(TABLE_CARD, $this->game->getPlayerPosition($playerId), MATERIAL_LOCATION_DECK);
        $factionCards = array_values(array_filter($cards, fn($card) => $card->faction == $faction));
        return $factionCards;
    }

    public function resetDecks() {
        $this->deck->moveAllCardsInLocation(MATERIAL_LOCATION_DISCARD, MATERIAL_LOCATION_DECK);
        $this->deck->moveAllCardsInLocation(MATERIAL_LOCATION_HAND, MATERIAL_LOCATION_DECK);
        $this->deck->moveAllCardsInLocation(MATERIAL_LOCATION_CARD, MATERIAL_LOCATION_DECK);
        $this->deck->moveAllCardsInLocation(MATERIAL_LOCATION_ENCOUNTER, MATERIAL_LOCATION_DECK);
        $this->deck->shuffle("deck");
        $this->dealHands(notify: true);
        $this->game->notifyCounterChange();
    }
}

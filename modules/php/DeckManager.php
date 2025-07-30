<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Deck;

class DeckManager extends \APP_DbObject {
    protected Deck $deck;
    protected $game;
    protected $cast;
    protected $castParameters;
    protected $materialType;
    protected $tableName;

    public function __construct(Game $game, string $tableName, Deck $deck, string $cast, string $materialType, array $castParameters = []) {
        $this->deck = $deck;
        $this->game = $game;
        $this->cast = __NAMESPACE__ . '\\objects\\' . $cast; //fully qualified name because of namespaces
        $this->castParameters = $castParameters;
        $this->materialType = $materialType;
        $this->tableName = $tableName;
    }

    public function createCards(array $cards, bool $shuffle = true, string $destination = 'deck') {
        $this->deck->createCards($cards, $destination);
        if ($shuffle)
            $this->deck->shuffle('deck');
    }

    public function shuffleLocationByTypeArg(string $location, int $typeArg): void {
        $cards = $this->getCardsOfTypeArgFromLocation(TABLE_CARD, $typeArg, $location);
        if (count($cards) > 0) {
            $shuffled = $this->game->getRandomSlice($cards, count($cards));
            foreach ($shuffled as $i => $card) {
                $this->deck->moveCard($card->id, $location, $i);
            }
        }
    }

    /**
     * Gets remaining cards count in deck (or in discard if 0 in deck)
     */
    public function getRemainingCardsInDeck(): int {
        $remaining = intval($this->deck->countCardInLocation('deck'));
        if ($remaining == 0) {
            $remaining = intval($this->deck->countCardInLocation('discard'));
        }
        return $remaining;
    }

    public function countCardsInLocation(string $location, int $locationArg): int {
        return intval($this->deck->countCardInLocation($location, $locationArg));
    }

    public function countCardsInDiscard(): int {
        return intval($this->deck->countCardInLocation('discard'));
    }

    public function getPlayerHandCount(int $playerId) {
        return $this->deck->countCardInLocation("hand", $playerId);
    }

    public function getDiscardCards() {
        return $this->cast($this->deck->getCardsInLocation("discard"));
    }

    public function getRiverCards() {
        return $this->cast($this->deck->getCardsInLocation("river"));
    }

    public function getTopOfDiscard() {
        return $this->castSingle($this->deck->getCardOnTop("discard"), true);
    }

    public function getTopOfLocation(string $location) {
        return $this->castSingle($this->deck->getCardOnTop($location), true);
    }

    public function getCardsOfType($type, ?int $typeArg = null) {
        return $this->cast($this->deck->getCardsOfType($type, $typeArg));
    }

    public function countCardsOfType(string $tableName, int $type) {
        $sql = "SELECT count(card_id) FROM $tableName where card_type = '$type'";
        return $this->game->getUniqueIntValueFromDB($sql);
    }

    public function getDeckCards() {
        return $this->cast($this->deck->getCardsInLocation("deck"));
    }

    public function getCardsInLocation(string $location, ?int $locationArg = null, ?string $orderBy = null) {
        return $this->cast($this->deck->getCardsInLocation($location, $locationArg, $orderBy));
    }

    public function getCardsOfTypeArgFromLocation(string $tableName, int $typeArg, string $location) {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName where card_location = '$location' and card_type_arg = '$typeArg'";
        return $this->cast($this->game->getCollectionFromDb($sql));
    }

    public function getCardOfTypeAndTypeArg(string $tableName, string|int $type, int $typeArg): CardiaCard|null {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName where card_type_arg = '$typeArg' and card_type = '$type'";
        return $this->castSingle($this->game->getObjectFromDB($sql), true);
    }

    public function getCardsOfTypeArgFromLocationOrderBy(string $tableName, int $typeArg, string $location, string $orderBy, bool $desc = false) {
        $direction = $desc ? 'desc' : 'asc';
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName 
        where card_location = '$location' and card_type_arg = $typeArg order by $orderBy $direction";
        return $this->cast($this->game->getCollectionFromDb($sql));
    }

    public function countCardsOfTypeArgFromLocation(string $tableName, int $typeArg, string $location) {
        $sql = "SELECT count(card_id) FROM $tableName where card_location = '$location' and card_type_arg = '$typeArg'";
        return $this->game->getUniqueIntValueFromDB($sql);
    }

    public function getCastedTopOfLocationForTypeArg(string $location, int $typeArg) {
        return  $this->castSingle($this->game->getTopOfLocationForTypeArg(TABLE_CARD, $location, $typeArg), true);
    }

    public function swapHands(int $playerFrom, int $playerTo) {
        $fromCards = $this->cast($this->deck->getCardsInLocation("hand", $playerFrom));
        $toCards = $this->cast($this->deck->getCardsInLocation("hand", $playerTo));

        $this->deck->moveCards($this->game->getIds($fromCards), "hand", $playerTo);
        $this->deck->moveCards($this->game->getIds($toCards), "hand", $playerFrom);

        $this->game->notifyAllPlayers('msg', clienttranslate('${player_name} swaps hands with ${player_name2}'), array(
            'player_name' => $this->game->getPlayerName($playerFrom),
            'player_name2' => $this->game->getPlayerName($playerTo),
        ));

        foreach ([$playerFrom, $playerTo] as $player) {
            $this->game->notifyPlayer($player, "materialMove", '', [
                'type' => $this->materialType,
                'from' => MATERIAL_LOCATION_HAND,
                'fromArg' => $playerTo,
                'to' => MATERIAL_LOCATION_HAND,
                'toArg' => $playerFrom,
                'material' => $this->cast($this->deck->getPlayerHand($playerFrom)),
            ]);

            $this->game->notifyPlayer($player, "materialMove", '', [
                'type' => $this->materialType,
                'from' => MATERIAL_LOCATION_HAND,
                'fromArg' => $playerFrom,
                'to' => MATERIAL_LOCATION_HAND,
                'toArg' => $playerTo,
                'material' => $this->cast($this->deck->getPlayerHand($playerTo)),
            ]);
        }
        $this->game->notifyCounterChange();
    }

    public function stealCard(int $thiefId, int $victimId) {
        $hand = $this->getPlayerHand($victimId);
        if ($hand) {
            $card = $this->game->getRandomValue($hand);
            $this->deck->moveCard($card->id, "hand", $thiefId);

            $this->game->notifyAllPlayers('msg', clienttranslate('${player_name} steals a card from ${player_name2}'), array(
                'player_name' => $this->game->getPlayerName($thiefId),
                'player_name2' => $this->game->getPlayerName($victimId),
            ));

            $this->game->notifyPlayer($victimId, "materialMove", '', [
                'type' => $this->materialType,
                'from' => MATERIAL_LOCATION_HAND,
                'to' => MATERIAL_LOCATION_DISCARD,
                'material' => [$card],
            ]);
            $this->game->notifyPlayer($thiefId, "materialMove", '', [
                'type' => $this->materialType,
                'from' => MATERIAL_LOCATION_HAND,
                'fromArg' => $victimId,
                'to' => MATERIAL_LOCATION_HAND,
                'toArg' => $thiefId,
                'material' => [$card],
            ]);

            $this->game->notifyCounterChange();
        } else {
            $this->game->notifyAllPlayers('msg', clienttranslate('${player_name2} has no card to be stolen'), array(
                'player_name2' => $this->game->getPlayerName($victimId),
            ));
        }
    }

    public function getPlayerHand(int $playerId) {
        return $this->cast($this->deck->getPlayerHand($playerId));
    }

    protected function cast(array $cards, bool $optional = false) {
        return array_values(
            array_map(function ($c) use ($optional) {
                return $this->castSingle($c, $optional);
            }, $cards)
        );
    }

    protected function castSingle($c, bool $optional = false) {
        if (!$optional && (!$c || !array_key_exists('id', $c))) {
            throw new \BgaSystemException("$this->cast doesn't exists " . json_encode($c));
        }
        return $c ? $this->newInstance($c) : null;
    }

    private function newInstance($c) {
        return $this->castParameters ? new $this->cast($c, $this->castParameters) : new $this->cast($c);
    }

    public function discardCard(int $playerId, int $cardId, $msg = "", $msgParameters) {
        $this->deck->playCard($cardId);
        $this->game->notifyWithName("materialMove",  $msg ? $msg : clienttranslate('${player_name} discards a card'), [
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_HAND,
            'to' => MATERIAL_LOCATION_DISCARD,
            'toArg' => $playerId,
            'material' => $this->cast([($this->deck->getCard($cardId))]),
            'i18n' => ['cardName', "ability"],
            ...$msgParameters,
            $playerId
        ]);
        $this->game->notifyCounterChange();
    }

    public function discardCards(array $cards) {
        $ids = $this->game->getIds($cards);
        $this->deck->moveCards($ids, "discard");
        $this->game->notifyWithName("materialMove",  "", [
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_HAND,
            'to' => MATERIAL_LOCATION_DISCARD,
            'material' => $this->cast($this->deck->getCards($ids)),
        ]);
        $this->game->notifyCounterChange();
    }

    public function getPlayersWithMoreCardsThan(int $playerId) {
        $cardsCount = $this->deck->countCardInLocation("hand", $playerId);
        $players = $this->game->getPlayers();
        $playersWithMore = [];
        foreach ($players as $otherPlayerId => $player) {
            if ($otherPlayerId != $playerId && $this->deck->countCardInLocation("hand", $otherPlayerId) > $cardsCount) {
                $playersWithMore[] = $otherPlayerId;
            }
        }
        return $playersWithMore;
    }

    public function getPlayersWithLessOrSameAmountOfCardsThan(int $playerId) {
        $cardsCount = $this->deck->countCardInLocation("hand", $playerId);
        $players = $this->game->getPlayers();
        $playersWithMore = [];
        foreach ($players as $otherPlayerId => $player) {
            if ($otherPlayerId != $playerId && $this->deck->countCardInLocation("hand", $otherPlayerId) <= $cardsCount) {
                $playersWithMore[] = $otherPlayerId;
            }
        }
        return $playersWithMore;
    }

    public function getPlayersWithCards() {
        $allPlayers = $this->game->getPlayers();
        $players = [];
        foreach ($allPlayers as $otherPlayerId => $player) {
            if ($this->deck->countCardInLocation("hand", $otherPlayerId) > 0) {
                $players[] = $otherPlayerId;
            }
        }
        return $players;
    }

    public function swapCardsBetweenPlayerHands(int $card1Id, int $card2Id) {
        $card1 = $this->castSingle($this->deck->getCard($card1Id));
        $card2 = $this->castSingle($this->deck->getCard($card2Id));
        $this->deck->moveCard($card1->id, $card2->location, $card2->location_arg);
        $this->deck->moveCard($card2->id, $card1->location, $card1->location_arg);

        $this->game->notifyAllPlayers("materialMove", '', [
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_HAND,
            'to' => MATERIAL_LOCATION_RIVER,
            'material' => [$this->castSingle($this->deck->getCard($card1Id))],
        ]);

        $this->game->notifyAllPlayers("materialMove", '', [
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_RIVER,
            'to' =>  MATERIAL_LOCATION_HAND,
            'toArg' => $card1->location_arg,
            'material' => [$this->castSingle($this->deck->getCard($card2Id))],
        ]);
    }

    public function pickAndDiscard($notify = true) {
        $newCard = $this->castSingle($this->deck->pickCardForLocation('deck', 'discard'));

        if ($notify) {
            $this->game->notifyAllPlayers('materialMove', "", [
                'type' => MATERIAL_TYPE_CARD,
                'from' => MATERIAL_LOCATION_DECK,
                'to' => MATERIAL_LOCATION_DISCARD,
                'material' => [$newCard],
            ]);
            $this->game->notifyCounterChange();
        }
        return $newCard;
    }

    public function moveCardToPlayerHand(int $cardId, int $playerId, bool $faceDown = false, string $notifMsg = null) {
        $this->deck->moveCard($cardId, "hand", $playerId);
        $card = $this->castSingle($this->deck->getCard($cardId));

        if ($faceDown) {
            $this->game->notifyPlayer($playerId, 'materialMove', "", [
                'type' => MATERIAL_TYPE_CARD,
                'from' => MATERIAL_LOCATION_DECK,
                'to' => MATERIAL_LOCATION_HAND,
                'toArg' => $this->game->getMostlyActivePlayerId(),
                'material' => [$card],
            ]);
            $this->game->notifyWithName('msg', $notifMsg ?? clienttranslate('${player_name} takes a card'), [
                'player_name' =>  $this->game->getPlayerName($playerId),
            ]);
        } else {
            $this->game->notifyWithName('materialMove',  $notifMsg ?? clienttranslate('${player_name} takes a card'), [ //${cardType}
                'type' => MATERIAL_TYPE_CARD,
                'from' => MATERIAL_LOCATION_RIVER,
                'to' => MATERIAL_LOCATION_HAND,
                'toArg' => $this->game->getMostlyActivePlayerId(),
                'cardType' => $card->type,
                'material' => [$card],
            ]);
        }
        $this->game->notifyCounterChange();
    }


    public function getFirstCardInLocation(string $location) {
        $cards = $this->deck->getCardsInLocation($location);
        return $cards[array_key_first($cards)] ?? null;
    }

    public function getFirstEmptySlotInLocation(int $locationMaxSize, $location = "river") {
        $i = 0;
        $full = true;
        while ($i < $locationMaxSize && $full) {
            $full = intval($this->deck->countCardInLocation($location, $i)) > 0;
            if ($full) $i++;
        }
        return $i;
    }


    public function replaceRiver() {
        $oldCards = $this->getRiverCards();
        $this->deck->moveCards($this->game->getIds($oldCards), "discard");
        $this->game->notifyAllPlayers('materialMove', "", [
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_RIVER,
            'to' => MATERIAL_LOCATION_DISCARD,
            'material' => $oldCards,
        ]);

        $this->initRiver(count($oldCards));
        $this->game->notifyAllPlayers('materialMove', "", [
            'type' => $this->materialType,
            'from' => MATERIAL_LOCATION_DECK,
            'to' => MATERIAL_LOCATION_RIVER,
            'material' => $this->getRiverCards(),
        ]);
    }

    /**
     * Fills river with cards.
     */
    public function initRiver(int $cardsCount) {
        $riverCards = array_values($this->deck->getCardsOnTop($cardsCount, "deck"));
        foreach ($riverCards as $i => $card) {
            $this->deck->moveCard($card["id"], "river", $i + 1);
        }
    }

    public function getCard(int $cardId, bool $optional = false) {
        return $this->castSingle($this->deck->getCard($cardId), $optional);
    }

    public function getCards(array $cardIds) {
        return $this->cast($this->deck->getCards($cardIds), false);
    }

    public function getCardOnLocation(string $location, bool $optional = false) {
        return $this->castSingle($this->getFirstCardInLocation($location), $optional);
    }

    public function shiftLocationArgsForCards(array $cards, int $shiftAmount) {
        $ids = $this->game->dbArrayParam($this->game->getIds($cards));
        $this->game->DbQuery("UPDATE $this->tableName SET card_location_arg = card_location_arg + $shiftAmount WHERE 'card_id' IN ($ids)");
    }
}

<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\PowerType;
use Bga\Games\Cardia\objects\TokenType;

/**
 * @property CardManager cardManager
 * @property TokenManager $tokenManager
 */
trait StateTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stDealInitialSetup() {
        $playersIds = $this->getPlayersIds();

        foreach ($playersIds as $playerId) {
            //$this->cardManager->pickInitialDestinationCards($playerId);
        }

        $this->gamestate->nextState('');
    }

    function stDuelReveal() {
        $playersIds = $this->getPlayersIds();
        $duelCount = $this->globals->inc(GLB_DUEL_COUNT, 1);
        $maxCard = null;
        $minCard = null;
        $stateTransition = 'finishDuel';

        foreach ($playersIds as $playerId) {
            $card = $this->getCardiaCardFromDb(json_decode($this->globals->get(GLB_LAST_CHOSEN_CARD . "_" . $playerId), true));
            $this->cardManager->playCard($card, $playerId, $duelCount);
            if ($minCard === null) {
                $minCard = $card;
                $maxCard = $card;
            }
            $value = $this->getCardValue($card);
            if ($value > $maxCard->modifiedValue) {
                $maxCard = $card;
            }
            if ($value < $minCard->modifiedValue) {
                $minCard = $card;
            }
        }

        if ($minCard->id != $maxCard->id) {
            $operator = ">";
            $this->notifyWithName('msg', clienttranslate('${cardName1} beats ${cardName2}: ${winnerValue} ${operator} ${looserValue}'), [
                'winnerValue' => $maxCard->modifiedValue,
                'looserValue' => $minCard->modifiedValue,
                'operator' => $operator,
                'cardName1' => $maxCard->name,
                'cardName2' => $minCard->name,
            ]);

            $this->tokenManager->addSignetOnCard($maxCard->id);
            $this->globals->set(GLB_ABILITY_TO_RESOLVE, $minCard->id);
            $stateTransition = 'looserAbility';
        } else {
            $this->notifyWithName('msg', clienttranslate('Tie on value: ${winnerValue}'), [
                'winnerValue' => $maxCard->modifiedValue,
            ]);
        }
        $this->gamestate->nextState($stateTransition);
    }

    function stLooserAbility() {
        $card = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        if ($this->isAbilityNeedingInteraction($card)) {
            $this->gamestate->nextState('interactiveAbility');
        } else {
            $this->applyAbility($card, $this->cardManager->getDuelsList());
            $this->gamestate->nextState('finishDuel');
        }
    }

    function isAbilityNeedingInteraction(CardiaCard $card): bool {
        $abilitiesNeedingInteraction = [VOID_MAGE, PALACE_GUARD, AMBUSHER, SWAMP_GUARDIAN, MAGISTRA, INVENTOR];
        return in_array($card->id, $abilitiesNeedingInteraction);
    }

    function applyAbility(CardiaCard $card, array $duels) {
        $this->notifyWithName('msg', clienttranslate('${cardName} ability'), [
            'cardName' => $card->name,
        ]);
        $this->dump('*******************applyAbility', $card->name);


        if ($card->powerType == PowerType::ONGOING) {
            $this->tokenManager->addOngoingTokenOnCard($card->id);
        }

        $opponentTypeArg = $card->type_arg == 1 ? 2 : 1;
        $opponentId = $this->getPlayerIdFromPosition($opponentTypeArg);
        switch ($card->type) {
            case HIRED_BLADE:
                $opposing = $this->cardManager->getOpposingCard($card, $duels);
                $this->discardDuelCard($card);
                $this->discardDuelCard($opposing);
                break;
            case MEDIATOR:
                $opposing = $this->cardManager->getOpposingCard($card, $duels);
                $this->tokenManager->discardTokenOfTypeOnCard($opposing, TokenType::SIGIL);
                break;
            case SABOTEUR:
                for ($i = 0; $i < 2; $i++) {
                    $top = $this->cardManager->getCastedTopOfLocationForTypeArg(MATERIAL_LOCATION_DECK, $opponentTypeArg);
                    if ($top) {
                        $this->cardManager->discardCard($opponentId, $top->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $top->name]);
                    }
                }
                break;
            case PUPPETEER:
                $opposing = $this->cardManager->getOpposingCard($card, $duels);
                $this->discardDuelCard($opposing);
                $opponentHand = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opposing->type_arg, MATERIAL_LOCATION_HAND);
                $replacement = $this->getRandomValue($opponentHand);

                $this->cardManager->moveCardToLocation($replacement, $opposing->location, $opponentId, true, $opponentId);
                //todo recalculate winner
                break;
        }
    }

    function discardDuelCard(CardiaCard $card) {
        $this->tokenManager->discardTokensOnDuelCard($card);
        $this->cardManager->discardDuelCard($card);
        $this->notifyWithName('msg', clienttranslate('${cardName} is discarded'), [
            'cardName' => $card->name,
        ]);
    }

    function getCardValue(CardiaCard &$card, bool $withModifiers = true): int {
        $value = $card->value;
        //$this->dump('*************getCardValue', $card);
        //$this->dump('*************initial value**', $value);
        if ($withModifiers) {
            $applies = true;
            if ($applies) {
                $value += $this->cardManager->getModifierValueOnCard($card->id);
                $card->modifiedValue = $value;
            }
        }

        $this->dump('*************final value**', $value);
        return $value;
    }

    /**
     * If only player has 5 signets or both have at least 5 signets but one player has more than the other, end of round.
     * @return void 
     */
    function stFinishDuel() {
        $winner = $this->getSignetCountWinner();
        if (!$winner) {
            $winner = $this->getNoPlayableCardWinner();
            if ($winner == -1) {
                self::notifyAllPlayers('msg', clienttranslate('No more cards to play for any player and tie on signets count, end of round'), []);
            }
        }

        $nextState = $winner ? 'nextRound' : 'chooseDuelCard';

        if ($winner) {
            $this->incPlayerScore($winner, 1, clienttranslate('${player_name} wins the round !'), ["player_name" => $this->getPlayerName($winner)]);
        } else {
            $this->cardManager->pickAdditionalCard();
        }
        $this->gamestate->nextState($nextState);
    }

    function getSignetCountWinner(): ?int {
        $playersIds = $this->getPlayersIds();
        $signetCounts = array_combine($playersIds, array_map(fn($id) => $this->tokenManager->getSignetCount($id), $playersIds));
        $winner = null;

        //filter players with at least 5 signets
        $playersWith5Signets = array_filter($signetCounts, fn($count) => $count >= 5);

        //check if every player from playersWith5Signets has the same signets count
        $tieOn5SignetsOrMore = count(array_unique($playersWith5Signets)) === 1;
        if (!$playersWith5Signets || $tieOn5SignetsOrMore) {
            //no winner yet
        } else {
            if (count($playersWith5Signets) == 1) {
                //if only player has 5 signets, he wins
                $winner = array_key_first($playersWith5Signets);
            } else {
                //winner is the player with the most signets
                $maxSignets = max($playersWith5Signets);
                $winners = array_keys(array_filter($playersWith5Signets, fn($count) => $count == $maxSignets));
                $winner = $winners[0];
            }
        }
        return $winner;
    }

    function getNoPlayableCardWinner() {
        $winner = null;
        $playersIds = $this->getPlayersIds();

        //if no card in hand and no card in deck, won’t be able to play
        $cardsCount = array_combine($playersIds, array_map(
            fn($id) => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($id), MATERIAL_LOCATION_DECK)
                + $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($id), MATERIAL_LOCATION_HAND),
            $playersIds
        ));

        //if only one player has cards, he wins
        $playersWithCards = array_filter($cardsCount, fn($count) => $count > 0);
        if (count($playersWithCards) == 1) {
            $winner = array_key_first($playersWithCards);
        } else if (count($playersWithCards) == 0) {
            //if no player has cards, the player with the most signets wins
            $playersIds = $this->getPlayersIds();
            $signetCounts = array_combine($playersIds, array_map(fn($id) => $this->tokenManager->getSignetCount($id), $playersIds));
            $maxSignets = max($signetCounts);
            $winners = array_keys(array_filter($signetCounts, fn($count) => $count == $maxSignets));
            if (count($winners) == 1) {
                $winner = $winners[0];
            } else {
                $winner = -1;
            }
        }
        return $winner;
    }

    function stNextRound() {
        $players = $this->loadPlayersBasicInfos();
        $currentRound = $this->globals->get(GLBL_ROUND);

        if ($this->hasReachedEndOfGameRequirements()) {
            if ($this->isStudio()) {
                $this->gamestate->nextState('debugEndGame');
            } else {
                $this->gamestate->nextState('endGame');
            }
        } else {
            $this->globals->set(GLB_DUEL_COUNT, 0);
            $this->globals->inc(GLBL_ROUND, 1);
            $currentRound++;

            self::notifyAllPlayers('msg', clienttranslate('&#10148; Round ${round}'), ["round" => $currentRound]);
            $this->cardManager->resetDecks();
            $this->gamestate->nextState('chooseDuelCard');
        }
    }

    function endOfRoundReset() {
        $this->globals->set(GLB_DUEL_COUNT, 0);
    }

    function hasReachedEndOfGameRequirements(): bool {
        $playersIds = $this->getPlayersIds();
        foreach ($playersIds as $playerId) {
            if ($this->getPlayerScore($playerId) == 2) {
                return true;
            }
        }
        return false;
    }

    function stNextPlayer() {
        $playerId = $this->getActivePlayerId();
        if (!$playerId) {
            $this->activateNextPlayerCustom();
            $this->gamestate->nextState('nextPlayer');
            return;
        }

        //$this->setGameStateValue(TICKETS_USED, 0);
        $lastTurn = intval($this->getGameStateValue(LAST_TURN));

        // check if it was last action from the last player or if there is no arrow left
        if ($lastTurn == $playerId || ($this->hasReachedEndOfGameRequirements($playerId) && $this->isLastPlayer($playerId))) {
            $this->gamestate->nextState('endScore');
        } else {
            //finishing round or playing normally
            $this->activateNextPlayerCustom();
            $this->gamestate->nextState('nextPlayer');
        }
    }

    /**
     * Activates next player, also giving him extra time.
     */
    function activateNextPlayerCustom() {
        $player_id = $this->activeNextPlayer();
        $this->giveExtraTime($player_id);
        $this->incStat(1, 'turns_number', $player_id);
        $this->incStat(1, 'turns_number');
        $this->notifyWithName('msg', clienttranslate('&#10148; Start of ${player_name}\'s turn'));
        //$this->makeSavepoint();
    }

    function stEndScore() {
        $this->score();

        if ($this->isStudio()) {
            $this->gamestate->nextState('debugEndGame');
        } else {
            $this->gamestate->nextState('endGame');
        }
    }

    function score() {
        $sql = "SELECT player_id id, player_score score, player_no playerNo FROM player ORDER BY player_no ASC";
        $players = $this->getCollectionFromDb($sql);

        // points gained during the game
        $totalScore = [];
        foreach ($players as $playerId => $playerDb) {
            $totalScore[$playerId] = intval($playerDb['score']);
        }

        //end of game points

        // failed destinations 
        /* $destinationsResults = [];
        $completedDestinationsCount = [];
        foreach ($players as $playerId => $playerDb) {
            $completedDestinationsCount[$playerId] = 0;
            $uncompletedDestinations = [];
            $completedDestinations = [];

            $destinations = $this->getDestinationsFromDb($this->destinations->getCardsInLocation('hand', $playerId));

            foreach ($destinations as &$destination) {
                $completed = boolval($this->getUniqueValueFromDb("SELECT `completed` FROM `destination` WHERE `card_id` = $destination->id"));
                if ($completed) {
                    $completedDestinationsCount[$playerId]++;
                    $completedDestinations[] = $destination;
                    $this->incStat(1, STAT_POINTS_WITH_PLAYER_COMPLETED_DESTINATIONS, $playerId);
                } else {
                    $totalScore[$playerId] += -1;
                    $this->incScore($playerId, -1);
                    if ($this->isDestinationRevealed($destination->id)) {
                        $totalScore[$playerId] += -1;
                        $this->incScore($playerId, -1);
                        $this->incStat(-1, STAT_POINTS_WITH_REVEALED_DESTINATIONS, $playerId);
                    }
                    $this->incStat(1, STAT_POINTS_LOST_WITH_UNCOMPLETED_DESTINATIONS, $playerId);
                    $uncompletedDestinations[] = $destination;
                }
            }

            $destinationsResults[$playerId] = $uncompletedDestinations;
        }
*/
        foreach ($players as $playerId => $playerDb) {
            static::DbQuery("UPDATE player SET `player_score` = $totalScore[$playerId] where `player_id` = $playerId");
            static::DbQuery("UPDATE player SET `player_score_aux` = `player_remaining_tickets` where `player_id` = $playerId");
        }

        $bestScore = max($totalScore);
        $playersWithScore = [];
        foreach ($players as $playerId => &$player) {
            $player['playerNo'] = intval($player['playerNo']);
            $player['ticketsCount'] = $this->getRemainingTicketsCount($playerId);
            $player['score'] = $totalScore[$playerId];
            $playersWithScore[$playerId] = $player;
        }
        $this->notifyAllPlayers('bestScore', '', [
            'bestScore' => $bestScore,
            'players' => array_values($playersWithScore),
        ]);

        // highlight winner(s)
        foreach ($totalScore as $playerId => $playerScore) {
            if ($playerScore == $bestScore) {
                $this->notifyAllPlayers('highlightWinnerScore', '', [
                    'playerId' => $playerId,
                ]);
            }
        }
    }
}

<?php

namespace Bga\Games\Cardia;

use Bga\GameFramework\Db\Globals;
use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\PowerType;
use Bga\Games\Cardia\objects\TokenType;
use BgaUserException;
use GameState;

/**
 * @property CardManager cardManager
 * @property TokenManager $tokenManager
 * @property GameState gamestate
 * @property Globals globals
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
        $stateTransition = 'finishDuel';
        $cards = [];
        foreach ($playersIds as $playerId) {
            $card = $this->getCardiaCardFromDb(json_decode($this->globals->get(GLB_LAST_CHOSEN_CARD . "_" . $playerId), true));
            $cards[] = $card;
            $this->cardManager->playCard($card, $playerId, $duelCount);

            $modifierToAdd = $this->globals->get(GLB_NEXT_CARD_MODIFIER . $playerId);
            if ($modifierToAdd) {
                $this->cardManager->incCardModifier($card, $modifierToAdd);
                $this->globals->delete(GLB_NEXT_CARD_MODIFIER . $playerId);
            }
        }

        $eval = $this->evaluateDuelValues($cards);

        if ($eval["hasWinner"]) {
            $this->globals->set(GLB_ABILITY_TO_RESOLVE, $eval["looser"]->id);
            $stateTransition = 'looserAbility';
        }

        $this->dump('*******************stDuelReveal', $stateTransition);
        $this->gamestate->nextState($stateTransition);
    }

    function evaluateDuelValues(array $cards) {
        $hasWinner = false;
        $maxCard = null;
        $minCard = null;
        foreach ($cards as $card) {
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
            $hasWinner = true;
            $operator = ">";
            $this->notifyWithName('msg', clienttranslate('${cardName1} beats ${cardName2}: ${winnerValue} ${operator} ${looserValue}'), [
                'winnerValue' => $maxCard->modifiedValue,
                'looserValue' => $minCard->modifiedValue,
                'operator' => $operator,
                'cardName1' => $maxCard->name,
                'cardName2' => $minCard->name,
            ]);

            $this->tokenManager->addSignetOnCard($maxCard->id, $minCard->id);
        } else {
            $this->notifyWithName('msg', clienttranslate('Tie on value: ${winnerValue}'), [
                'winnerValue' => $maxCard->modifiedValue,
            ]);
        }
        return ["hasWinner" => $hasWinner, "winner" => $maxCard, "looser" => $minCard];
    }

    function stLooserAbility() {
        $card = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        if ($this->isAbilityNeedingInteraction($card)) {
            $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $this->getPlayerIdFromPosition($card->type_arg));
            $this->gamestate->nextState('interactiveAbility');
        } else {
            $this->applyAbility($card, $this->cardManager->getDuelsList(), $this->tokenManager->getSignetsOnCards(), $this->globals->get(GLB_DUEL_COUNT));
            if ($card->type != DJINN) {
                $this->gamestate->nextState('finishDuel');
            }
        }
    }

    function stActivatePlayerForAbility() {
        $playerId = $this->globals->get(GLB_PLAYER_TO_ACTIVATE);
        $this->gamestate->changeActivePlayer($playerId);
        $this->gamestate->nextState($this->globals->get(GLB_STEP_2) ? 'interactiveAbilityStep2' : 'interactiveAbility');
    }

    function isAbilityNeedingInteraction(CardiaCard $card): bool {
        $abilitiesNeedingInteraction = [VOID_MAGE, PALACE_GUARD, AMBUSHER, SWAMP_GUARDIAN, MAGISTRA, INVENTOR];
        return in_array($card->type, $abilitiesNeedingInteraction);
    }

    /**
     * 
     * @param CardiaCard $card 
     * @param array $duels 
     * @param CardiaToken[] $signets 
     * @param int duelNumber
     * @return void 
     * @throws BgaUserException 
     */
    function applyAbility(CardiaCard $card, array $duels, array $signets, int $duelNumber) {
        $this->notifyWithName('msg', clienttranslate('${cardName} ability'), [
            'cardName' => $card->name,
        ]);
        $this->dump('*******************applyAbility', $card->name);

        if ($card->powerType == PowerType::ONGOING) {
            $this->tokenManager->addOngoingTokenOnCard($card->id);
        }

        $opponentTypeArg = $card->type_arg == 1 ? 2 : 1;
        $opponentId = $this->getPlayerIdFromPosition($opponentTypeArg);
        $playerId = $this->getPlayerIdFromPosition($card->type_arg);
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
                $opponentHand = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opposing->type_arg, MATERIAL_LOCATION_HAND);
                $replacement = $this->getRandomValue($opponentHand);
                $this->discardDuelCard($opposing,  clienttranslate('${cardName} is replaced by ${cardName2}'), ["cardName" => $opposing->name, "cardName2" => $replacement->name]);
                $this->cardManager->moveCardToLocation($replacement, $opposing->location, $opposing->location_arg, true, $opponentId);
                $this->evaluateDuelValues([$card, $replacement]);
                break;
            case TREASURER:
                if ($duelNumber > 1) {
                    $previousDuelCards = $duels[$duelNumber - 1];
                    $cardsWithSignet = array_filter($previousDuelCards, function ($c) use ($signets) {
                        return !empty(array_filter($signets, fn($s) => $s->location == MATERIAL_LOCATION_CARD && $s->location_arg == $c->id));
                    });

                    $winningCard = reset($cardsWithSignet);
                    if ($winningCard) {
                        $this->tokenManager->addSignetOnCard($winningCard->id, null);
                    }
                }
                break;
            case DJINN:
                $this->stFinishDuel($playerId);
                break;
            case SURGEON:
                $value = -5;
                $this->globals->set(GLB_NEXT_CARD_MODIFIER . $playerId, $value);
                $this->notifyWithName('nextCardModifier', "", [
                    'value' => $value,
                    'playerId' => $playerId,
                    'playerPosition' => $this->getPlayerPosition($playerId),
                ]);
                break;
            case CLOCKMAKER:
                $value = 3;
                $this->globals->set(GLB_NEXT_CARD_MODIFIER . $playerId, $value);
                $this->notifyWithName('nextCardModifier', "", [
                    'value' => $value,
                    'playerId' => $playerId,
                    'playerPosition' => $this->getPlayerPosition($playerId),
                ]);
                if ($duelNumber > 1) {
                    $previousCard = $duels[$duelNumber - 1][$playerId];
                    $this->cardManager->incCardModifier($previousCard, $value);
                    $this->evaluateDuelValues([$previousCard, $this->cardManager->getOpposingCard($previousCard, $this->cardManager->getDuelsList())]);
                }
                break;
        }
    }

    function applyInteractiveAbility(CardiaCard $interactiveAbility, ?Faction $faction, ?CardiaCard $card, ?string $option) {

        $this->notifyWithName('msg', clienttranslate('${cardName} ability'), [
            'cardName' => $interactiveAbility->name,
        ]);
        $this->dump('*******************applyAbility', $interactiveAbility->name);

        $opponentTypeArg = $interactiveAbility->type_arg == 1 ? 2 : 1;
        $opponentId = $this->getPlayerIdFromPosition($opponentTypeArg);
        $playerId = $this->getPlayerIdFromPosition($interactiveAbility->type_arg);
        switch ($interactiveAbility->type) {
            case PALACE_GUARD:
                //faction has been chosen but the opponent still needs to choose a card
                if ($this->isAbilityPossible($interactiveAbility, $opponentId, $faction)) {
                    $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $opponentId);
                    $this->globals->set(GLB_STEP_2, true);
                    $this->gamestate->nextState('interactiveAbilityStep2');
                }
                break;
            case INVENTOR:
                //first selected card gets a +3
                $this->cardManager->incCardModifier($interactiveAbility, 3);
                $this->globals->set(GLB_INVENTOR_PLUS_CARD, $card->id);
                //still needs to select another card
                $this->globals->set(GLB_STEP_2, true);
                $this->gamestate->nextState('interactiveAbilityStep2');
                break;
            case VOID_MAGE:
                if ($option == "removeModifiers") {
                    $this->cardManager->updateCardModifier($card, 0);
                    $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                } else {
                    $this->tokenManager->discardTokenOfTypeOnCard($card, TokenType::ONGOING);
                    //todo reevaluate everything
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case AMBUSHER:
                $cards = $this->cardManager->getFactionCardsInHand($opponentId, $faction);
                foreach ($cards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case SWAMP_GUARDIAN:
                $opposingCard = $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList());
                $this->cardManager->discardDuelCard($opposingCard);
                $this->cardManager->moveCardToLocation($card, MATERIAL_LOCATION_HAND, $playerId, true, $playerId);
                //todo reorder duels
                $this->gamestate->nextState('finishDuel');
                break;
        }
    }

    function applyInteractiveAbilityStep2(CardiaCard $interactiveAbility,  ?CardiaCard $card) {
        switch ($interactiveAbility->type) {
            case PALACE_GUARD:
                if ($card) {
                    $this->cardManager->discardDuelCard($card);
                } else {
                    //add +7 influence
                    $this->cardManager->incCardModifier($interactiveAbility, 7);
                    $this->evaluateDuelValues([$interactiveAbility, $this->cardManager->getOpposingCard($interactiveAbility, $this->cardManager->getDuelsList())]);
                }
                break;
            case INVENTOR:
                //second selected card gets a -3
                $this->cardManager->incCardModifier($card, -3);
                $firstModif = $this->cardManager->getCard($this->globals->get(GLB_INVENTOR_PLUS_CARD));
                $this->globals->delete(GLB_INVENTOR_PLUS_CARD);
                $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                if ($firstModif->location_arg != $card->location_arg) {
                    $this->evaluateDuelValues([$firstModif, $this->cardManager->getOpposingCard($firstModif, $this->cardManager->getDuelsList())]);
                }
                break;
        }
        $this->gamestate->nextState('finishDuel');
    }

    function isAbilityPossible(CardiaCard $card, int $playerToApply, Faction $faction): bool {
        switch ($card->type) {
            case PALACE_GUARD:
                return count($this->cardManager->getFactionCardsInHand($playerToApply, $faction)) > 0;

            default:
                return false;
        }
    }

    function discardDuelCard(CardiaCard $card, $msg = "", $msgArgs = []) {
        $this->tokenManager->discardTokensOnDuelCard($card);
        $this->cardManager->discardDuelCard($card);
        $this->notifyWithName('msg', $msg ? $msg : clienttranslate('${cardName} is discarded'), [
            'cardName' => $card->name,
            'i18n' => ['cardName', "cardName2"],
            ...$msgArgs
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
    function stFinishDuel($djinnWinner = null) {
        $this->globals->delete(GLB_SELECTED_CARD_ID);
        $this->globals->delete(GLB_SELECTED_FACTION);
        $this->globals->delete(GLB_STEP_2);

        $winner = $djinnWinner ?? $this->getSignetCountWinner();
        if (!$winner) {
            $winner = $this->getNoPlayableCardWinner();
            if ($winner == -1) {
                self::notifyAllPlayers('msg', clienttranslate('No more cards to play for any player and tie on signets count, end of round'), []);
            }
        }

        $nextState = $winner ? 'nextRound' : 'chooseDuelCard';

        if ($winner) {
            $playerName = $this->getPlayerName($winner);
            $this->incPlayerScore($winner, 1, clienttranslate('${player_name} wins the round !'), ["player_name" => $playerName]);
            $this->notifyAllPlayers('importantMessage', "", ["message" => clienttranslate('${player_name} wins the round'), "type" => "POSITIVE", "temporary" => true, "player_name" => $playerName]);
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

            self::notifyAllPlayers('newRound', clienttranslate('&#10148; Round ${round}'), ["round" => $currentRound]);
            $this->cardManager->resetDecks();
            $this->tokenManager->resetTokens();
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

<?php

namespace Bga\Games\Cardia;

use Bga\GameFramework\Db\Globals;
use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\PowerType;
use Bga\Games\Cardia\objects\TokenType;
use BgaSystemException;
use BgaUserException;
use feException;
use GameState;
use Random\RandomException;

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

    function getCardToReveal(int $playerId): ?CardiaCard {
        $card = null;
        if ($this->getScenery() == FOGGY_SWAMP) {
            $duelCount = $this->globals->get(GLB_DUEL_COUNT);
            //$this->dump('*****************getCardToReveal **duelCount', $duelCount);
            if ($duelCount > 1) {
                $duels = $this->cardManager->getDuelsList();
                //$this->dump('*******************duels', $duels);
                $card = $duels[$duelCount - 1][$playerId];
            }
        } else {
            $card = $this->getCardiaCardFromDb(json_decode($this->globals->get(GLB_LAST_CHOSEN_CARD . "_" . $playerId), true));
        }
        return $card;
    }

    function getCardsToReveal(): array {
        $cards = [];
        $duels = $this->cardManager->getDuelsList();
        $duelCount = $this->globals->get(GLB_DUEL_COUNT);
        //$this->dump('*****************getCardSSSSToReveal **duelCount', $duelCount);
        //$this->dump('*******************duels', $duels);
        if ($this->getScenery() == FOGGY_SWAMP) {
            if ($duelCount > 1) {
                $cards = array_values($duels[$duelCount - 1]);
            }
        } else {
            $cards = array_values($duels[$duelCount]);
        }
        return $cards;
    }
    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */
    function stDuelReveal() {
        $players = $this->getPlayers();
        $duelCount = $this->globals->get(GLB_DUEL_COUNT);
        $stateTransition = 'evaluateDuel';
        $immediateLoosers = [];
        $currentRound = $this->globals->get(GLB_ROUND);
        $this->incStat(1, "game_encounters_round_$currentRound");

        //pause to let time for the playCard notif to be processed and card to be displayed on the back before revealing it
        $this->notify->all('simplePause', '', ['time' => 800]);

        foreach ($players as $playerId => $player) {
            $card = $this->getCardToReveal($playerId);
            $this->cardManager->updateCardRevealed($card->id, true);

            $this->notify->all("materialMove", clienttranslate('${player_name} reveals ${cardName}'), [
                'playerId' => $playerId,
                'player_name' => $this->getPlayerName($playerId),
                'type' => MATERIAL_TYPE_CARD,
                'from' => MATERIAL_LOCATION_HAND,
                'to' => MATERIAL_LOCATION_ENCOUNTER,
                'toArg' => $card->location_arg,
                'material' => [$card],
                'cardName' =>  $card->name,
                'i18n' => ['cardName'],
            ]);

            $this->dump('******************getCardToReveal*', $card);
            if ($this->getScenery() == AUCTION_HOUSE) {
                $revealedCardValue = $this->getCardValue($card, true);
                $duels = $this->cardManager->getDuelsList();
                if (isset($duels[$duelCount - 1])) {
                    $previousCard =  $duels[$duelCount - 1][$playerId];
                    if ($revealedCardValue < $this->getCardValue($previousCard, true)) {
                        $this->notifyLocationPower();
                        $this->cardManager->discardTopOfDeck($playerId, $player["player_no"], clienttranslate('Auction house : ${player_name} discards ${cardName}'), ["player_name" => $player["player_name"]]);
                    }
                }
            }

            if ($this->getScenery() == HAUNTED_CATACOMBS) {
                $revealedCardValue = $card->faction;
                $duels = $this->cardManager->getDuelsList();
                if (isset($duels[$duelCount - 1])) {
                    $previousCard =  $duels[$duelCount - 1][$playerId];
                    if ($card->faction == $previousCard->faction) {
                        //immediatly loose the round
                        $this->notifyLocationPower();
                        $immediateLoosers[] = $playerId;
                    }
                }
            }
        }

        if ($immediateLoosers) {
            if (count($immediateLoosers) == 1) {
                $this->globals->set(GLB_ROUND_EVERYONE_LOOSES, false);
                $this->globals->set(GLB_ROUND_WINNERS, [$this->getOpponentId(reset($immediateLoosers))]);
                $this->stFinishDuel();
            } else {
                $this->globals->set(GLB_ROUND_EVERYONE_LOOSES, true);
                $this->globals->set(GLB_ROUND_WINNERS, []);
                $this->stFinishDuel();
            }
        } else {
            $players = $this->getPlayers();
            foreach ($players as $playerId => $player) {
                $card = $this->getCardToReveal($playerId);
                if ($card) {
                    $modifierToAdd = $this->globals->get(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL . $playerId, 0);
                    if (($modifierToAdd && $this->getScenery() != FOGGY_SWAMP)
                        || ($modifierToAdd && $this->getScenery() == FOGGY_SWAMP && $this->globals->has(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL_COUNTDOWN . $playerId) && $this->globals->inc(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL_COUNTDOWN . $playerId, -1) == 0)
                    ) {
                        $this->gamestate->changeActivePlayer($playerId);
                        $stateTransition = "librarianAbility";
                    }

                    $faction = Faction::tryFrom($this->globals->get(GLB_BLACKMAILER_FACTION . $playerId, 0));
                    if (($faction && $this->getScenery() != FOGGY_SWAMP)
                        || ($faction && $this->getScenery() == FOGGY_SWAMP && $this->globals->has(GLB_BLACKMAILER_COUNTDOWN . $playerId) && $this->globals->inc(GLB_BLACKMAILER_COUNTDOWN . $playerId, -1) == 0)
                    ) {
                        if ($card->faction != $faction) {
                            $this->gamestate->changeActivePlayer($playerId);
                            $stateTransition = "blackmailerDiscard";
                        } else {
                            $this->globals->delete(GLB_BLACKMAILER_FACTION . $playerId);
                            $this->globals->delete(GLB_BLACKMAILER_COUNTDOWN . $playerId);
                        }
                    }
                }
            }
        }
        if (!$immediateLoosers) {
            $this->gamestate->nextState($stateTransition);
        }
    }

    function stDuelEvaluation() {
        $stateTransition = 'finishDuel';
        $cards = $this->getCardsToReveal();
        $eval = $this->evaluateDuelValues($cards, false);

        if ($eval["hasWinner"]) {
            $this->globals->set(GLB_ABILITY_TO_RESOLVE, $eval["looser"]->id);
            $stateTransition = 'looserAbility';
        } else {
            $this->globals->set(GLB_ABILITY_TO_RESOLVE, null);
        }

        if (!$eval["interrupt"]) {
            //$this->dump('*******************stDuelReveal', $stateTransition);
            $this->gamestate->nextState($stateTransition);
        } else {
            if ($eval["shortcutState"]) {
                $this->gamestate->nextState($eval["shortcutState"]);
            }
        }
    }

    function evaluateDuelValues(array $cards, $reevaluate = true) {
        $hasWinner = false;
        $maxCard = null;
        $minCard = null;
        $interrupt = false;
        $currentRound = $this->globals->get(GLB_ROUND);

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

        //$duelDescription = join(' vs ', array_map(fn($c) => $c->name, $cards));
        //$this->dump('********************evaluateDuelValues', $duelDescription);
        //$this->dump('*******************hasWinner', $minCard->id != $maxCard->id);
        //$this->dump('*******************Winner', $maxCard->name);

        $mediatorTie = false;
        if ($reevaluate && ($this->isCardGivenRoleActive(MEDIATOR, reset($cards)) || $this->isCardGivenRoleActive(MEDIATOR, end($cards)))) {
            $mediatorTie = true;
        }
        //$this->dump('*******************mediatorTie', $mediatorTie);

        if (!$mediatorTie && $minCard->id != $maxCard->id) {
            $hasWinner = true;
            $winningPlayerId = $this->getPlayerIdFromPosition($maxCard->type_arg);
            $loosingPlayerId = $this->getPlayerIdFromPosition($minCard->type_arg);
            $operator = ">";
            $this->notifyWithName('duelResult', clienttranslate('${cardName1} beats ${cardName2}: ${winnerValue} ${operator} ${looserValue}'), [
                'winnerValue' => $maxCard->modifiedValue,
                'looserValue' => $minCard->modifiedValue,
                'operator' => $operator,
                'cardName1' => $maxCard->name,
                'cardName2' => $minCard->name,
                'winningCard' => $maxCard,
                //'loosingCard' => $minCard,
                'i18n' => ["cardName1", "cardName2"],
            ]);


            $wasWinning = count($this->tokenManager->getSignetsOnCard($maxCard->id)) > 0;
            $hadSignet = $this->tokenManager->hasSignet($maxCard->id);
            $signetOwnerChanged = $this->addSignetOnCard($maxCard, $minCard);
            //$this->dump('*******************signetOwnerChanged', $signetOwnerChanged);
            if ($signetOwnerChanged || !$hadSignet) {
                $this->applyTreasurerAbilityIfNeeded($maxCard, $maxCard->location_arg, $minCard->id);
            }
            if (!$wasWinning) {
                $this->addSerpentTempleDiscarder($maxCard->location_arg, $loosingPlayerId);
            }

            if ($minCard->type == ARISTOCRAT) {
                $this->tokenManager->discardTokenOfTypeOnCard($minCard, TokenType::SIGIL);
            }
        } else {

            //tie->remove signets if any
            foreach ($cards as $card) {
                $this->tokenManager->discardTokenOfTypeOnCard($card, TokenType::SIGIL);
            }

            if ($mediatorTie) {
                $this->notifyWithName('msg', clienttranslate('${cardName1} VS ${cardName2}: Mediator tie'), [
                    'cardName1' => reset($cards)->name,
                    'cardName2' => end($cards)->name,
                    'i18n' => ["cardName1", "cardName2"]
                ]);
            } else {
                $this->notifyWithName('msg', clienttranslate('${cardName1} VS ${cardName2}: Tie on value ${winnerValue}'), [
                    'winnerValue' => $maxCard->modifiedValue,
                    'cardName1' => reset($cards)->name,
                    'cardName2' => end($cards)->name,
                    'i18n' => ["cardName1", "cardName2"],
                ]);
            }


            $this->applyJudgeAbilityIfNeeded($cards);
        }

        if ($this->array_every($cards, fn($c) => $c->type == COUNSELOR) && $this->hasEveryoneActiveCardInPlay(COUNSELOR)) {
            $this->notifyWithName('msg', clienttranslate('2 activated counselors in the same encounter cancel each other out'), []);
        } else {
            $players = $this->getPlayersIds();
            foreach ($players as $playerId) {
                $counselor = $this->isActiveCardInPlay(COUNSELOR, $playerId);
                if ($counselor) {
                    //check if this card is immediately before the counselor
                    $myCard = $this->getFirstElementInArray(array_filter($cards, fn($c) => $c->type_arg == $this->getPlayerPosition($playerId)));
                    $opponentCard = $this->getFirstElementInArray(array_filter($cards, fn($c) => $c->type_arg == ($myCard->type_arg == 1 ? 2 : 1)));
                    if ($counselor && $counselor->location_arg == $myCard->location_arg + 1) {
                        $opponentCard = $this->getFirstElementInArray(array_filter($cards, fn($c) => $c->type_arg != $this->getPlayerPosition($playerId)));
                        $signetOwnerChanged = $this->addSignetOnCard($myCard, $opponentCard);
                        $this->addSerpentTempleDiscarder($myCard->location_arg, $this->getOpponentId($playerId));
                        if ($signetOwnerChanged) {
                            $this->notifyWithName('msg', clienttranslate('${cardName1} beats ${cardName2}'), [
                                'cardName1' => $myCard->name,
                                'cardName2' => $opponentCard->name,
                                'i18n' => ["cardName1", "cardName2"],
                            ]);
                        }
                    }
                }
            }
        }

        $shortcutState = null;
        if ($this->getScenery() == FOUNDERS_DAY) {
            $finalWinners = $this->checkForFoundersDayWinners();
            if (count($finalWinners) > 0) {
                $this->globals->set(GLB_ROUND_EVERYONE_LOOSES, false);
                $this->globals->set(GLB_ROUND_WINNERS, $finalWinners);
                $interrupt = true; //to not resolve looser ability
                $shortcutState = 'finishDuel';
            }
        }
        $result = ["hasWinner" => $hasWinner, "winner" => $maxCard, "looser" => $minCard, "interrupt" => $interrupt, "shortcutState" => $shortcutState];

        return $result;
    }

    function checkForFoundersDayWinners() {
        $finalWinners = $this->getPlayersHavingSuccessiveWins(3);
        if (count($finalWinners) > 0) {
            $this->notifyLocationPower();
            $this->notifyAllPlayers('importantMessage', "", ["message" => clienttranslate('3 successive wins, end of round'), "type" => "POSITIVE", "temporary" => true,]);
        }
        return $finalWinners;
    }

    function applyJudgeAbilityIfNeeded($duelCards) {
        //check if any or both players have played judge
        $players = $this->getPlayersIds();
        foreach ($players as $playerId) {
            $judge = $this->isActiveCardInPlay(JUDGE, $playerId);
            if ($judge) {
                $this->dump('*******************judge active for ', $playerId);
                $this->notifyWithName('power', clienttranslate('${abilityName} ability: ${playerName} wins the encounter'), [
                    "ability" => $judge,
                    "abilityName" => $judge->name,
                    "playerName" => $this->getPlayerName($playerId),
                    'location' => false,
                    'i18n' => ['ability']
                ]);
                $myCard = $this->getFirstElementInArray(array_filter($duelCards, fn($c) => $c->type_arg == $this->getPlayerPosition($playerId)));
                $this->addSignetOnCard($myCard, null);
            }
        }
    }

    function addSerpentTempleDiscarder(int $encounterNumber, int $opponentPlayerId) {
        if ($this->getScenery() == SERPENT_TEMPLE) {
            $isPreviousDuel = $encounterNumber < $this->globals->get(GLB_DUEL_COUNT);
            if ($isPreviousDuel) {
                $this->notifyLocationPower();
                $discarders = $this->globals->get(GLB_SERPENT_TEMPLE_DISCARDERS);
                array_push($discarders, $opponentPlayerId);
                $this->globals->set(GLB_SERPENT_TEMPLE_DISCARDERS, $discarders);
            }
        }
    }

    function getPlayersHavingSuccessiveWins(int $minimumWins) {
        $players = $this->loadPlayersBasicInfos();
        $withEnoughSuccessiveWins = [];
        foreach ($players as $playerId => $player) {
            //$this->dump('*******************$playerId', $playerId);
            $cards = $this->cardManager->getCardsOfTypeArgFromLocationOrderBy(TABLE_CARD, $player["player_no"], MATERIAL_LOCATION_ENCOUNTER, "card_location_arg");
            //$this->dump('*******************$cards', $cards);
            //check if player has 3 successive cards with signets looping through cards
            if (count($cards) < $minimumWins) {
                continue;
            }

            $successiveWins = 0;
            //a win is a card with signets
            for ($i = 0; $i < count($cards); $i++) {
                $card = $cards[$i];
                $signets = $this->tokenManager->getSignetsOnCard($card->id);
                if (count($signets) > 0) {
                    $successiveWins++;
                } else {
                    $successiveWins = 0;
                }
                if ($successiveWins == $minimumWins) {
                    $withEnoughSuccessiveWins[] = $playerId;
                    //$this->dump('*******************adding', $playerId);
                    break;  // Add this break to stop checking more cards once we've found enough successive wins
                }
            }
        }
        //$this->dump('*******************$withEnoughSuccessiveWins', $withEnoughSuccessiveWins);
        return $withEnoughSuccessiveWins;
    }

    function isActiveCardInPlay($cardType, $playerId) {
        $card = $this->cardManager->getCardInPlay($cardType, $playerId);
        if ($card && $card->powerType == PowerType::ONGOING) {
            //check is ongoing card is still active
            if (!$this->tokenManager->hasOngoingToken($card->id)) {
                $card = null;
            }
        }
        return $card;
    }

    function isCardGivenRoleActive(int $cardType, CardiaCard $card): bool {
        $ret = $card->type == $cardType;
        if ($ret && $card->powerType == PowerType::ONGOING) {
            $ret = $this->tokenManager->hasOngoingToken($card->id);
        }
        return $ret;
    }

    function hasEveryoneActiveCardInPlay(int $cardType): bool {
        $players = $this->getPlayersIds();
        $hasEveryoneActiveCardInPlay = true;
        foreach ($players as $playerId) {
            $card = $this->isActiveCardInPlay($cardType, $playerId);
            if (!$card) {
                $hasEveryoneActiveCardInPlay = false;
                break;
            }
        }
        return $hasEveryoneActiveCardInPlay;
    }

    function copyAbility(CardiaCard &$card, int $abilityToCopy) {
        //$this->dump('*******************ability copied from ', $card->name);
        $cardInfo = $this->CARDIA_CARDS[$abilityToCopy];
        //$this->dump('*******************ability copied to ', $cardInfo->name);
        $card->type = $abilityToCopy;
        $card->name = clienttranslate("{$card->name} copying {$cardInfo->name}");
    }

    function stLooserAbility() {
        $card = $this->getAbilityToResolve();

        $possible = $this->isAbilityPossible($card, $this->getPlayerIdFromPosition($card->type_arg), null);
        //$this->dump('*******************stLooserAbility', $card->name);
        //$this->dump('*******************isAbilityNeedingInteraction', $this->isAbilityNeedingInteraction($card));
        //$this->dump('*******************isAbilityPossible', $possible);
        if ($this->isAbilityNeedingInteraction($card)) {

            if ($possible) {
                if ($card->type == REVOLUTIONARY || $card->type == SUCCESSOR) {
                    //opponent is acting
                    $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $this->getOpponentId($this->getPlayerIdFromPosition($card->type_arg)));
                } else {
                    //card owner is acting
                    $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $this->getPlayerIdFromPosition($card->type_arg));
                }
                $this->gamestate->nextState('interactiveAbility');
            } else {
                $this->notifyWithName('msg', clienttranslate('${cardName} ability impossible to resolve'), [
                    'cardName' => $card->name,
                    'i18n' => ['cardName'],
                ]);

                if ($card->type != DJINN) {
                    $this->gamestate->nextState('finishDuel');
                }
            }
        } else {
            $winnersIfAny = null;
            if ($possible) {
                $winnersIfAny = $this->applyAbility($card, $this->cardManager->getDuelsList(), $this->tokenManager->getSignetsOnCards(), $card->location_arg);
            } else {
                $this->notifyWithName('msg', clienttranslate('${cardName} ability impossible to resolve'), [
                    'cardName' => $card->name,
                    'i18n' => ['cardName'],
                ]);
            }
            if ($winnersIfAny) {
                $this->globals->set(GLB_ROUND_EVERYONE_LOOSES, false);
                $this->globals->set(GLB_ROUND_WINNERS, $winnersIfAny);
                $this->stFinishDuel();
            } else {
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
        $abilitiesNeedingInteraction = [
            VOID_MAGE,
            PALACE_GUARD,
            AMBUSHER,
            SWAMP_GUARDIAN,
            MAGISTRA,
            INVENTOR,
            KINESIS_MAGE,
            ENVOY,
            REVOLUTIONARY,
            PRODIGY,
            BLACKMAILER,
            ILLUSIONIST,
            WITCH_KING,
            ELEMENTAL,
            SUCCESSOR
        ];
        return in_array($card->type, $abilitiesNeedingInteraction);
    }

    /**
     * 
     * @param CardiaCard $ability 
     * @param array $duels 
     * @param CardiaToken[] $signets 
     * @param int duelNumber
     * @return void 
     * @throws BgaUserException 
     */
    function applyAbility(CardiaCard $ability, array $duels, array $signets, int $duelNumber) {
        $this->notifyWithName('power', clienttranslate('${cardName} ability triggered'), [
            'ability' => $ability,
            'cardName' => $ability->name,
            'location' => false,
            'i18n' => ['cardName'],
        ]);
        $this->dump('*******************applyAbility', $ability->name);

        if ($ability->powerType == PowerType::ONGOING) {
            $this->tokenManager->addOngoingTokenOnCard($ability->id);
        }

        $opponentTypeArg = $ability->type_arg == 1 ? 2 : 1;
        $opponentId = $this->getPlayerIdFromPosition($opponentTypeArg);
        $playerId = $this->getPlayerIdFromPosition($ability->type_arg);
        $winnersIfAny = null;
        switch ($ability->type) {
            case HIRED_BLADE:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $this->discardDuelCard($ability);
                $this->discardDuelCard($opposing);
                $this->reorderDuels($duelNumber);
                break;
            case MEDIATOR:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $this->tokenManager->discardTokenOfTypeOnCard($opposing, TokenType::SIGIL);
                $this->applyJudgeAbilityIfNeeded([$ability, $opposing]);
                break;
            case SABOTEUR:
                for ($i = 0; $i < 2; $i++) {
                    $this->cardManager->discardTopOfDeck($opponentId, $opponentTypeArg);
                }
                break;
            case PUPPETEER:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $opponentHand = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opposing->type_arg, MATERIAL_LOCATION_HAND);
                if ($opponentHand) {
                    $replacement = $this->getRandomValue($opponentHand);
                    $this->discardDuelCard($opposing,  clienttranslate('${cardName} is replaced by ${cardName2}'), ["cardName" => $opposing->name, "cardName2" => $replacement->name]);
                    $this->cardManager->moveCardToLocation($replacement, $opposing->location, $opposing->location_arg, true, $opponentId);
                    $this->cardManager->updateCardRevealed($replacement->id, true);
                    $this->evaluateDuelValues([$ability, $replacement]);
                    $this->cardManager->replenishHands();
                } else {
                    $winnersIfAny = [$playerId];
                    $this->notifyAllPlayers('importantMessage', "", ["message" => clienttranslate('${player_name} has no card in hand to apply puppeteer ability and loses the round'), "type" => "NEGATIVE", "temporary" => true, 'playerId' => $opponentId, "player_name" => $this->getPlayerName($opponentId)]);
                    $this->notifyAllPlayers('msg', clienttranslate('${player_name} has no card in hand to apply puppeteer ability and loses the round'), ["playerId" => $opponentId, "player_name" => $this->getPlayerName($opponentId)]);
                }
                break;
            case TREASURER:
                if ($duelNumber > 1) {
                    $winningCard = $this->getWinningCard($duelNumber - 1);
                    if ($winningCard) {
                        $this->addSignetOnCard($winningCard, null, true);
                    }
                }
                break;
            case DJINN:
                $winnersIfAny = [$playerId];
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
                    $this->incCardModifier($previousCard, $value);
                    $this->evaluateDuelValues([$previousCard, $this->cardManager->getOpposingCard($previousCard, $this->cardManager->getDuelsList())]);
                }
                break;
            case JUDGE:
                $duels = $this->cardManager->getDuelsList();
                $tied = $this->getTiedDuels($duels);
                foreach ($tied as $duelNumber => $duel) {
                    $this->tokenManager->addSignetOnCard($duel[$playerId], null);
                    $this->applyTreasurerAbilityIfNeeded($duel[$playerId], $duelNumber);
                    $this->addSerpentTempleDiscarder($duel[$playerId]->location_arg, $this->getOpponentId($playerId));
                }
                break;
            case POISONER:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $opposingValue = $this->getCardValue($opposing, false);
                $cardValue = $this->getCardValue($ability);
                //opposingValue - newOpposingModifier = cardValue
                $newOpposingModifier = $opposingValue - $cardValue;
                $this->cardManager->updateCardModifier($opposing,  $newOpposingModifier * -1);
                $this->evaluateDuelValues([$ability, $opposing]);
                break;
            case TAX_COLLECTOR:
                $this->incCardModifier($ability,  4);
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $this->evaluateDuelValues([$ability, $opposing]);
                break;
            case ENGINEER:
                $value = 5;
                $this->globals->set(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED . $playerId, $value);
                if ($this->getScenery() == FOGGY_SWAMP) {
                    $this->globals->set(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED_COUNTDOWN . $playerId, 3);
                } else {
                    $this->globals->set(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED_COUNTDOWN . $playerId, 2);
                }
                $this->notifyWithName('nextCardModifier', "", [
                    'value' => $value,
                    'playerId' => $playerId,
                    'playerPosition' => $this->getPlayerPosition($playerId),
                    'disabled' => true,
                ]);
                break;
            case COUNSELOR:
                if ($duelNumber > 1) {
                    $previousDuelCards = $duels[$duelNumber - 1];
                    $signetOwnerChanged = $this->addSignetOnCard($previousDuelCards[$playerId], $previousDuelCards[$opponentId]);
                    $this->addSerpentTempleDiscarder($previousDuelCards[$playerId]->location_arg, $opponentId);
                    if ($signetOwnerChanged) {
                        $this->notifyWithName('msg', clienttranslate('${cardName1} beats ${cardName2}'), [
                            'cardName1' => $previousDuelCards[$playerId]->name,
                            'cardName2' => $previousDuelCards[$opponentId]->name,
                            'i18n' => ["cardName1", "cardName2"],
                        ]);
                    }
                    //todo check if other ongoin power
                }
                break;
            case LIBRARIAN:
                $this->globals->set(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL . $playerId, true);
                if ($this->getScenery() == FOGGY_SWAMP) {
                    $this->globals->set(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL_COUNTDOWN . $playerId, 2); //one reveal to wait
                } else {
                    $this->globals->set(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL_COUNTDOWN . $playerId, 1); //on next reveal
                }
                break;
        }
        return $winnersIfAny;
    }

    public function reorderDuels(int $encounter) {
        $this->cardManager->reorderDuels($encounter);
        $duels = $this->cardManager->getDuelsList();
        if (isset($duels[$encounter - 1])) {
            foreach ($this->getPlayersIds() as $pId) {
                //$this->dump('*******************applyTreasurerAbilityIfNeeded', $duels[$encounter - 1][$pId]->name);
                $card = $duels[$encounter - 1][$pId];
                if ($this->tokenManager->hasSignet($card->id)) {
                    $this->applyTreasurerAbilityIfNeeded($card, $encounter - 1);
                }
            }
        }
    }

    function applyTreasurerAbilityIfNeeded(CardiaCard $card, int $duelNumber, ?int $opposingCardId = null) {
        foreach ($this->getPlayersIds() as $pId) {
            $treasurer = $this->isActiveCardInPlay(TREASURER, $pId);
            if ($treasurer && $treasurer->location_arg == $duelNumber + 1) {
                $this->notifyWithName('power', clienttranslate('${cardName} ability triggered'), [
                    'ability' => $treasurer,
                    'cardName' => $treasurer->name,
                    'location' => false,
                    'i18n' => ['cardName']
                ]);
                $this->addSignetOnCard($card, $opposingCardId ? $this->cardManager->getCard($opposingCardId) : null, true);
            }
        }
    }

    public function addSignetOnCard(CardiaCard $card, ?CardiaCard $opposingCard, ?bool $severalPossible = false): bool {
        $signetOwnerChanged = $this->tokenManager->addSignetOnCard($card, $opposingCard, $severalPossible);
        if ($signetOwnerChanged) {
            $card = $this->cardManager->getCard($card->id);
            $playerId = $this->getPlayerIdFromPosition($card->type_arg);
            $this->incStat(1, "game_stolen_signets", $playerId);
        }

        if ($this->isCardGivenRoleActive(ARISTOCRAT, $card)) {
            $this->tokenManager->addSignetOnCard($card, null, true);
            $this->notifyWithName('power', clienttranslate('${cardName} ability triggered'), [
                'ability' => $card,
                'cardName' => $card->name,
                'location' => false,
                'i18n' => ['cardName']
            ]);
        }
        return $signetOwnerChanged;
    }
    /**
     * @param array<mixed, array<mixed, object|null>> $duels 
     * @return void 
     */
    function getTiedDuelsOnSignets($duels) {
        $tied = $duels;
        $cardsWithSignet = array_map(fn($s) => $s->location_arg, $this->tokenManager->getSignetsOnCards());
        foreach ($duels as $duelNumber => $duel) {
            $duelCards = array_values($duel);
            $tie = !$this->array_some($duelCards, function ($c) use ($cardsWithSignet) {
                return in_array($c->id, $cardsWithSignet);
            });
            if (!$tie || !$this->cardManager->isCardRevealed(reset($duelCards)->id)) {
                unset($tied[$duelNumber]);
            }
        }
        //$this->dump('*******************getTiedDuelsOnSignets', $tied);
        return $tied;
    }

    /**
     * 
     * @param mixed $duels 
     * @return getTiedDuelsOnSignets + getTiedDuelsOnValues 
     */
    function getTiedDuels($duels): array {
        return $this->getTiedDuelsOnSignets($duels) + $this->getTiedDuelsOnValues($duels) + $this->getMediatorTies();
    }

    function getMediatorTies(): array {
        $ties = [];
        foreach ($this->getPlayers() as $playerId => $player) {
            $mediator = $this->isActiveCardInPlay(MEDIATOR, $playerId);
            if ($mediator) {
                $ties[$mediator->location_arg] = [$playerId => $mediator, $this->getOpponentId($playerId) => $this->cardManager->getOpposingCard($mediator, $this->cardManager->getDuelsList())];
            }
        }
        //$this->dump('*******************getMediatorTies', $ties);
        return $ties;
    }

    public function getWinningCard(int $duelNumber) {
        $duelCards = $this->cardManager->getDuelsList()[$duelNumber];
        $signets = $this->tokenManager->getSignetsOnCards();
        $cardsWithSignet = array_filter($duelCards, function ($c) use ($signets) {
            return !empty(array_filter($signets, fn($s) => $s->location == MATERIAL_LOCATION_CARD && $s->location_arg == $c->id));
        });

        $winningCard = reset($cardsWithSignet);
        return $winningCard;
    }

    /**
     * @param array<mixed, array<mixed, object|null>> $duels 
     * @return void 
     */
    function getTiedDuelsOnValues($duels) {
        $tied = $duels;
        foreach ($duels as $duelNumber => $duel) {
            $duelCards = array_values($duel);
            $card1 = array_pop($duelCards);
            $card2 = array_pop($duelCards);
            $tie = $this->getCardValue($card1) == $this->getCardValue($card2);
            if (!$tie || !$this->cardManager->isCardRevealed($card1->id)) {
                unset($tied[$duelNumber]);
            }
        }
        //$this->dump('*******************getTiedDuelsOnValues', $tied);
        return $tied;
    }

    /**
     * 
     * @param CardiaCard $interactiveAbility 
     * @param null|Faction $faction 
     * @param CardiaCard[] $cards 
     * @param null|string $option 
     * @return void 
     * @throws feException 
     * @throws BgaUserException 
     * @throws BgaSystemException 
     * @throws RandomException 
     */
    function applyInteractiveAbility(CardiaCard $interactiveAbility, ?Faction $faction, array $cards, ?string $option) {

        $this->notifyWithName('msg', clienttranslate('${cardName} ability'), [
            'cardName' => $interactiveAbility->name,
            'i18n' => ["cardName"]
        ]);
        //$this->dump('*******************applyInteractiveAbility', $interactiveAbility->name);
        foreach ($cards as $card) {
            // $this->dump('*******************on', $card?->name);
        }

        $opponentTypeArg = $interactiveAbility->type_arg == 1 ? 2 : 1;
        $opponentId = $this->getPlayerIdFromPosition($opponentTypeArg);
        $playerId = $this->getPlayerIdFromPosition($interactiveAbility->type_arg);
        $card = reset($cards) ?? null;
        switch ($interactiveAbility->type) {
            case PALACE_GUARD:
                //faction has been chosen but the opponent still needs to choose a card
                $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $opponentId);
                $this->globals->set(GLB_STEP_2, true);
                $this->gamestate->nextState('interactiveAbilityStep2');
                break;
            case INVENTOR:
                //first selected card gets a +3
                $this->incCardModifier($card, 3);
                $this->globals->set(GLB_INVENTOR_PLUS_CARD, $card->id);
                //still needs to select another card
                $this->globals->set(GLB_STEP_2, true);
                $this->gamestate->nextState('interactiveAbilityStep2');
                break;
            case VOID_MAGE:
                if ($option == "removeModifiers") {
                    $this->cardManager->updateCardModifier($card, 0);
                    $this->notifyWithName('msg', clienttranslate('${player_name} removes modifiers from ${cardName}'), [
                        'cardName' => $card->name,
                        'i18n' => ['cardName']
                    ], $playerId);
                    $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                } else {
                    $tokenCount = $this->tokenManager->discardTokenOfTypeOnCard($card, TokenType::ONGOING);
                    if ($tokenCount > 0) {
                        $this->notifyWithName('msg', clienttranslate('${player_name} removes an ongoing token from ${cardName}'), [
                            'cardName' => $card->name,
                            'i18n' => ['cardName']
                        ], $playerId);
                        $this->onRemovingOngoingTokenOnCard($card);
                    }
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case AMBUSHER:
                $involvedCards = $this->cardManager->getFactionCardsInHand($opponentId, $faction);
                if ($involvedCards) {
                    foreach ($involvedCards as $c) {
                        $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "playerId" => $opponentId]);
                        $this->cardManager->replenishHands();
                    }
                    $this->onCardInHandChange();
                } else {
                    $this->notifyWithName('msg', clienttranslate('${player_name} has no ${factionColor} faction card'), ['playerId' => $opponentId, "factionColor" => $this->getColorName($faction)]);
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case SWAMP_GUARDIAN:
                $encounter = $card->location_arg;
                $opposingCard = $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList());

                //cards goes back to hand and not discard, but the effect are still applied like a discard: lose ongoing tokens and modifiers
                $ongoingDiscardedTokens = $this->tokenManager->discardTokensOnDuelCard($card);
                if ($ongoingDiscardedTokens > 0) {
                    $this->onRemovingOngoingTokenOnCard($card);
                }
                $this->cardManager->updateCardModifier($card, 0);
                //order is critical here, do discard action before moving any card and mess with the duels
                $this->discardDuelCard($opposingCard);
                $this->cardManager->moveCardToLocation($card, MATERIAL_LOCATION_HAND, $playerId, true, $playerId, clienttranslate('${player_name} takes ${cardName} back in hand'), ["cardName" => $card->name]);
                $this->reorderDuels($encounter);
                $this->gamestate->nextState('finishDuel');
                break;
            case MAGISTRA:
                $this->globals->set(GLB_ABILITY_TO_RESOLVE, $interactiveAbility->id);
                $this->globals->set(GLB_ABILITY_TO_RESOLVE_COPIED_TYPE, $card->type);
                $this->stLooserAbility();
                break;
            case ILLUSIONIST:
                $this->globals->set(GLB_ABILITY_TO_RESOLVE, $card->id);
                $this->globals->delete(GLB_ABILITY_TO_RESOLVE_COPIED_TYPE); //fix 177828
                $this->stLooserAbility();
                break;
            case PRODIGY:
                $this->incCardModifier($card, 3);
                $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                $this->gamestate->nextState('finishDuel');
                break;
            case KINESIS_MAGE:
                $this->globals->set(GLB_KINESIS_SOURCE_CARD, $card->id);
                //still needs to select destination
                $this->globals->set(GLB_STEP_2, true);
                $this->gamestate->nextState('interactiveAbilityStep2');
                break;
            case ENVOY:
                $value = -3;
                if ($card) {
                    $this->incCardModifier($card, $value);
                    $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                } else {
                    $this->globals->set(GLB_NEXT_CARD_MODIFIER . $playerId, $value);
                    $this->notifyWithName('nextCardModifier', "", [
                        'value' => $value,
                        'playerId' => $playerId,
                        'playerPosition' => $this->getPlayerPosition($playerId),
                    ]);
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case WITCH_KING:
                $involvedCards = $this->cardManager->getFactionCardsInHand($opponentId, $faction);
                foreach ($involvedCards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $involvedCards = $this->cardManager->getFactionCardsInDeck($opponentId, $faction);
                foreach ($involvedCards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $this->cardManager->shuffleLocationByTypeArg(MATERIAL_LOCATION_DECK, $opponentTypeArg);
                $this->onCardInHandChange();
                $this->gamestate->nextState('finishDuel');
                break;
            case REVOLUTIONARY:
                foreach ($cards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $this->cardManager->addCardsToHand(2, $opponentId, $opponentTypeArg, true);
                $this->gamestate->nextState('finishDuel');
                break;
            case SUCCESSOR:
                $involvedCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opponentTypeArg, MATERIAL_LOCATION_HAND);
                foreach ($involvedCards as $c) {
                    if (!in_array($c->id, array_map(fn($paramCard) => $paramCard->id, $cards))) {
                        $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                    }
                }
                $involvedCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opponentTypeArg, MATERIAL_LOCATION_DECK);
                foreach ($involvedCards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case BLACKMAILER:
                $this->globals->set(GLB_BLACKMAILER_FACTION . $opponentId, $faction->value);
                $this->notifyWithName('msg', clienttranslate('${player_name} chooses ${factionName} faction'), [
                    'factionName' => $this->getColorName($faction),
                    'playerId' => $playerId,
                    'i18n' => ['factionName'],
                ]);
                if ($this->getScenery() == FOGGY_SWAMP) {
                    $this->globals->set(GLB_BLACKMAILER_COUNTDOWN . $opponentId, 2); //one reveal to wait
                } else {
                    $this->globals->set(GLB_BLACKMAILER_COUNTDOWN . $opponentId, 1); //on next reveal
                }

                $this->gamestate->nextState('finishDuel');
                break;
            case ELEMENTAL:
                $this->cardManager->discardCard($playerId, $card->id, clienttranslate('${player_name} discards ${cardName} and copies its effect'), ["cardName" => $card->name, "player_name" => $this->getPlayerName($playerId)]);
                $this->globals->set(GLB_ABILITY_TO_RESOLVE, $interactiveAbility->id);
                $this->globals->set(GLB_ABILITY_TO_RESOLVE_COPIED_TYPE, $card->type);
                $this->stLooserAbility();
                break;
        }
    }

    function applyInteractiveAbilityStep2(CardiaCard $interactiveAbility, ?CardiaCard $card) {
        switch ($interactiveAbility->type) {
            case PALACE_GUARD:
                if ($card) {
                    $this->discardDuelCard($card);
                    $this->cardManager->replenishHands();
                } else {
                    //add +7 influence
                    $this->incCardModifier($interactiveAbility, 7);
                    $this->evaluateDuelValues([$interactiveAbility, $this->cardManager->getOpposingCard($interactiveAbility, $this->cardManager->getDuelsList())]);
                }
                break;
            case INVENTOR:
                //second selected card gets a -3
                $this->incCardModifier($card, -3);
                $firstModif = $this->cardManager->getCard($this->globals->get(GLB_INVENTOR_PLUS_CARD));
                $this->globals->delete(GLB_INVENTOR_PLUS_CARD);
                $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                if ($firstModif->location_arg != $card->location_arg) {
                    $this->evaluateDuelValues([$firstModif, $this->cardManager->getOpposingCard($firstModif, $this->cardManager->getDuelsList())]);
                }
                break;
            case KINESIS_MAGE:
                $source = $this->cardManager->getCard($this->globals->get(GLB_KINESIS_SOURCE_CARD));
                $destination = $card;
                $modifiers = $this->cardManager->getModifierValueOnCard($source->id);
                $duels = $this->cardManager->getDuelsList();
                $reevaluate = false;
                if ($modifiers != 0) {
                    $this->cardManager->updateCardModifier($source, 0);
                    $this->cardManager->updateCardModifier($destination, $modifiers);
                    $reevaluate = true;
                }
                $tokens = $this->tokenManager->getOngoingTokensOnCards();
                $tokens = array_values(array_filter($this->tokenManager->getOngoingTokensOnCards(), fn($c) => $c->location_arg == $source->id));
                if ($tokens) {
                    $reevaluate = true;
                    foreach ($tokens as $token) {
                        $this->tokenManager->discardTokenOfTypeOnCard($source, TokenType::ONGOING);
                        $this->onRemovingOngoingTokenOnCard($source);
                        $this->tokenManager->addOngoingTokenOnCard($destination->id);
                    }
                }

                $this->notifyWithName('message', clienttranslate('${player_name} moves modifiers and ongoing tokens from ${cardName} to ${cardName2}'), [
                    'cardName' => $source->name,
                    'cardName2' => $destination->name,
                    'i18n' => ['cardName', "cardName2"],
                ]);

                if ($reevaluate) {
                    $this->evaluateDuelValues([$destination, $this->cardManager->getOpposingCard($destination, $duels)]);
                    $this->evaluateDuelValues([$source, $this->cardManager->getOpposingCard($source, $duels)]);
                }
                break;
        }
        $this->gamestate->nextState('finishDuel');
    }

    function isAbilityPossible(CardiaCard $card, int $playerToApply, ?Faction $faction): bool {
        $cardOwner = $this->getPlayerIdFromPosition($card->type_arg);
        switch ($card->type) {
            case PALACE_GUARD:
                //don’t pass when there are no cards of the required faction, since it’s giving information to the opponent
                return !$faction;
            case MAGISTRA:
            case SWAMP_GUARDIAN:
            case ILLUSIONIST:
            case ELEMENTAL:
                return !empty($this->getSelectableCards($card, $cardOwner));
            case REVOLUTIONARY:
                return !empty($this->getSelectableCards($card, $this->getOpponentId($cardOwner)));
            case KINESIS_MAGE:
                return count($this->getSelectableCards($card, $cardOwner)) >= 2;
            default:
                return true;
        }
    }

    function incCardModifier(CardiaCard $card, int $modifier): void {
        $duels = $this->cardManager->getDuelsList();
        if (isset($duels[$card->location_arg])) {
            $previousTies = $this->getTiedDuelsOnValues($duels);
            $wasTie = isset($previousTies[$card->location_arg]);
            if ($wasTie) {
                foreach ($this->getPlayers() as $playerId => $player) {
                    $hasToRemoveSignet = $this->isActiveCardInPlay(JUDGE, $playerId);
                    if ($hasToRemoveSignet) {
                        $this->tokenManager->discardTokenOfTypeOnCard($previousTies[$card->location_arg][$playerId], TokenType::SIGIL);
                        $this->notifyWithName('message', clienttranslate('Tie is broken, ${player_name}’s judge cease to apply'), [], $playerId);
                    }
                }
            }
        }
        $this->cardManager->incCardModifier($card, $modifier);
    }

    function onRemovingOngoingTokenOnCard(CardiaCard $card) {
        $duels = $this->cardManager->getDuelsList();
        $opposingCard = $this->cardManager->getOpposingCard($card, $duels);
        switch ($card->type) {
            case MEDIATOR:
                $this->evaluateDuelValues([$card, $opposingCard]);
                break;
            case JUDGE:
                //normal ties on numbers 
                $ties = $this->getTiedDuels($duels);
                if ($ties) {
                    foreach ($ties as $duelNumber => $duel) {
                        $signetToRemoveCard = $duel[$this->getPlayerIdFromPosition($card->type_arg)];
                        $this->tokenManager->discardTokenOfTypeOnCard($signetToRemoveCard, TokenType::SIGIL);
                    }
                }
                $myMediator = $this->isActiveCardInPlay(MEDIATOR, $this->getPlayerIdFromPosition($card->type_arg));
                if ($myMediator) {
                    $this->tokenManager->discardTokenOfTypeOnCard($myMediator, TokenType::SIGIL);
                }
                $opponentMediator = $this->isActiveCardInPlay(MEDIATOR, $this->getPlayerIdFromPosition($opposingCard->type_arg));
                if ($opponentMediator) {
                    $this->tokenManager->discardTokenOfTypeOnCard($this->cardManager->getOpposingCard($opponentMediator, $duels), TokenType::SIGIL);
                }
                break;
            case TREASURER:
                $winningCard = $this->getWinningCard($card->location_arg - 1);
                if ($winningCard) {
                    $this->tokenManager->discardTokenOfTypeOnCard($winningCard, TokenType::SIGIL, true);
                }
                break;
            case ARISTOCRAT:
                $this->tokenManager->discardTokenOfTypeOnCard($card, TokenType::SIGIL, true);
                break;
            case COUNSELOR:
                $previousDuel = $card->location_arg - 1;
                if ($previousDuel > 0) {
                    $this->evaluateDuelValues($this->cardManager->getDuelsList()[$previousDuel]);
                }
                break;
            case MECHANICAL_DJINN:
                //nothing to do
                break;

            default:
                throw new BgaSystemException("unexpected ongoing card type: " . $card->type);
        }
        $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
    }

    function discardDuelCard(CardiaCard $card, $msg = "", $msgArgs = []) {
        $ongoingDiscardedTokens = $this->tokenManager->discardTokensOnDuelCard($card);
        if ($ongoingDiscardedTokens > 0) {
            $this->onRemovingOngoingTokenOnCard($card);
        }
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

        //$this->dump('*************card**', $card->name);
        //$this->dump('*************final value**', $value);
        return $value;
    }

    function stActivatePlayersToChooseDuelCard() {
        $ability = $this->getAbilityToResolve();
        if ($ability && $ability->type == FORTUNE_TELLER) {
            $this->gamestate->setPlayersMultiactive([$this->getPlayerIdFromPosition($ability->type_arg)], "duelReveal", true);
        } else {
            $this->gamestate->setAllPlayersMultiactive();
        }
    }

    /**
     * If only player has 5 signets or both have at least 5 signets but one player has more than the other, end of round.
     * @param array|null $winners forced winners, probably because of an ability
     * @param bool $everyoneLooses Flag indicating if all players lose the round
     * @return void 
     */
    function stFinishDuel() {
        $everyoneLooses = $this->globals->get(GLB_ROUND_EVERYONE_LOOSES, false);
        $roundWinners = $this->globals->get(GLB_ROUND_WINNERS);

        //add engineer influence if any
        $duels = $this->cardManager->getDuelsList();
        $anyModif = false;
        foreach ($this->getPlayers() as $playerId => $players) {
            $modifierToAdd = $this->globals->get(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED . $playerId, 0);
            if ($modifierToAdd != 0) {
                $playerCard = $this->getCardToReveal($playerId);
                if (
                    $this->globals->has(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED_COUNTDOWN . $playerId)
                    && $this->globals->inc(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED_COUNTDOWN . $playerId, -1) == 0
                ) {
                    $anyModif = true;
                    $this->incCardModifier($playerCard, $modifierToAdd);
                    $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED . $playerId);
                    $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED_COUNTDOWN . $playerId);
                }
            }

            $this->giveExtraTime($playerId);
        }
        if ($anyModif) {
            $evaluatedDuelCards = $this->getCardsToReveal();
            $this->evaluateDuelValues($evaluatedDuelCards);
        }

        //handle mechanical djinn if in play
        foreach ($this->getPlayers() as $playerId => $players) {
            if ($djinn = $this->isActiveCardInPlay(MECHANICAL_DJINN, $playerId)) {
                //check if this card is immediately following the djinn
                if ($djinn && isset($duels[$djinn->location_arg + 1]) && $this->tokenManager->hasSignet($duels[$djinn->location_arg + 1][$playerId]->id)) {
                    //win the game
                    $roundWinners = [$playerId];
                    $this->notifyWithName('power', clienttranslate('${cardName} ability triggered'), [
                        'ability' => $djinn,
                        'cardName' => $djinn->name,
                        'location' => false,
                        'i18n' => ['cardName']
                    ]);
                    break;
                }
            }
        }

        $this->updateMaxSignetsInARow();

        //reset data
        $this->globals->delete(GLB_SELECTED_CARD_ID);
        $this->globals->delete(GLB_SELECTED_FACTION);
        $this->globals->delete(GLB_STEP_2);
        $this->globals->delete(GLB_ABILITY_TO_RESOLVE_COPIED_TYPE);

        if (!$roundWinners) {
            if ($this->getScenery() == FOUNDERS_DAY) {
                //check is there is winners with founders day at this moment (could be that judge has been applied)
                $roundWinners = $this->checkForFoundersDayWinners();
            } else if ($this->getScenery() == SERPENT_TEMPLE) {
                //apply serpent temple discard
                $discarders = $this->globals->get(GLB_SERPENT_TEMPLE_DISCARDERS);
                if ($discarders) {
                    $discarderPlayer = array_shift($discarders);
                    if ($discarderPlayer) {
                        if ($this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($discarderPlayer), MATERIAL_LOCATION_HAND) > 0) {
                            $this->globals->set(GLB_SERPENT_TEMPLE_DISCARDERS, $discarders);
                            $this->gamestate->changeActivePlayer($discarderPlayer);
                            $this->gamestate->nextState("serpentTempleDiscard");
                            return;
                        }
                    }
                }
            }
        }

        if (!$roundWinners) {
            $signetWinner = $this->getSignetCountWinner();
            if ($signetWinner) {
                $roundWinners = [$signetWinner];
            }
        }

        if (!$roundWinners && !$everyoneLooses) {
            $noMoreCardsResult = $this->getNoMoreCardsToPlayWinnersAndLoosers($everyoneLooses);
            $roundWinners = $noMoreCardsResult['winners'];
            $everyoneLooses = $noMoreCardsResult['everyoneLooses'];
        }

        if ($everyoneLooses) {
            $roundWinners = [];
        }
        $this->notifyWinnersOrLoosers($roundWinners, $everyoneLooses);

        //$this->dump('*******************winners', $winners);
        $endOfRound = $roundWinners || $everyoneLooses;
        $nextState = $endOfRound ? 'nextRound' : 'chooseDuelCard';

        if (!$endOfRound) {
            //we continue to play
            $ability =  $this->getAbilityToResolve();
            if ($ability && $ability->type == FORTUNE_TELLER) {
                $this->gamestate->changeActivePlayer($this->getOpponentId($this->getPlayerIdFromPosition($ability->type_arg)));
                $nextState = 'chooseFortuneTellerCard';
            }

            if ($this->getOriginalAbilityToResolveType() == ELEMENTAL) {
                $this->cardManager->pickAdditionalCard();
            }
            $this->globals->delete(GLB_ABILITY_TO_RESOLVE_COPIED_TYPE);

            $location = $this->getScenery();
            if ($location == BAZAAR) {
                $this->cardManager->replenishHands();
            } else {
                $this->cardManager->pickAdditionalCard();
            }

            if ($location == GRAND_LIBRARY || $location == SCRAPYARD) {
                $this->notifyLocationPower();
                $this->cardManager->pickAdditionalCard(); //get one more card
            }

            if ($location == SCRAPYARD) {
                $this->globals->set(GLB_NEXT_STATE_AFTER_SCRAPYARD, $nextState);
                $nextState = 'chooseScrapyardCard';
            }
        }
        $this->globals->inc(GLB_DUEL_COUNT, 1);
        $this->gamestate->nextState($nextState);
    }

    function notifyWinnersOrLoosers(array $winners, bool $everyoneLooses) {
        if ($everyoneLooses) {
            $this->notifyAllPlayers('importantMessage', "", ["message" => clienttranslate('Everyone looses, end of round'), "type" => "NEGATIVE", "temporary" => true,]);
        }
        if ($winners) {
            foreach ($winners as $winner) {
                if ($winner) {
                    $this->setRoundWinner($winner);
                }
            }
        }
    }

    function getNoMoreCardsToPlayWinnersAndLoosers(bool $everyoneLooses, bool $checkOnlyHand = false) {
        $winners = [];
        $withCard = $this->getNoPlayableCardWinner($checkOnlyHand);
        if ($withCard && $withCard > -1) {
            $winners = [$withCard];
            $loser = $this->getOpponentId($withCard);
            $this->notifyWithName("msg",  clienttranslate('${player_name} has no more card to play'), [], $loser);
            $this->notifyWithName('importantMessage', "", ["message" => clienttranslate('${player_name} has no more card to play'), "type" => "NEGATIVE", "temporary" => true, "player_name" => $this->getPlayerName($loser)], $loser);
        } else {
            if ($withCard && $withCard == -1) {
                $msg = clienttranslate('No more cards to play for any player and tie on signets count, end of round');
                self::notifyAllPlayers('msg', $msg, []);
                $this->notifyWithName('importantMessage', "", ["message" => $msg, "type" => "NEGATIVE", "temporary" => true,]);

                $everyoneLooses = true;
            }
        }
        return [
            'winners' => $winners,
            'everyoneLooses' => $everyoneLooses,
        ];
    }

    function updateMaxSignetsInARow() {
        $playersIds = $this->getPlayers();
        foreach ($playersIds as $playerId => $player) {
            //map signets to their card ids to get encounter number
            $signets = $this->tokenManager->getSignetsOnPlayerCards($player["player_no"]);
            $cardIds = array_unique(array_map(fn($s) => $s->location_arg, $signets)); //a card can have multiple signets
            $cards = $this->cardManager->getCards($cardIds);

            $maxSignetCount = 0;
            $currentCount = 0;
            $previousSignet = null;
            foreach ($signets as $signet) {
                $signetLocation = $this->getFirstElementInArray(array_filter($cards, fn($c) => $c->id == $signet->location_arg))->location_arg;
                $previousSignetLocation = $previousSignet ? $this->getFirstElementInArray(array_filter($cards, fn($c) => $c->id == $previousSignet->location_arg))->location_arg : -1;

                if ($previousSignet !== null && $signetLocation == $previousSignetLocation + 1) {
                    $currentCount++;
                } else {
                    $currentCount = 1; // restart count on gap
                }
                $previousSignet = $signet;
                $maxSignetCount = max($maxSignetCount, $currentCount);
            }
            if ($maxSignetCount > 0) {
                $this->setStat(max($this->getStat("game_max_signets_in_a_row", $playerId), $maxSignetCount), "game_max_signets_in_a_row", $playerId);
            }
        }
    }

    function notifyLocationPower() {
        $this->notifyWithName('power', clienttranslate('${cardName} effect triggered'), [
            'cardName' => $this->LOCATIONS[$this->getScenery()],
            'location' => true,
            'i18n' => ['cardName'],
        ]);
    }

    function onCardInHandChange() {
        $this->cardManager->replenishHands(); //if bazaar
    }

    function setRoundWinner(int $playerId) {
        $playerName = $this->getPlayerName($playerId);
        $this->incPlayerScore($playerId, 1, clienttranslate('${player_name} wins the round !'), ["player_name" => $playerName]);
        $this->notifyAllPlayers('importantMessage', "", ["message" => clienttranslate('${player_name} wins the round'), "type" => "POSITIVE", "temporary" => true, "player_name" => $playerName]);
    }

    function getSignetCountWinner(): ?int {
        $playersIds = $this->getPlayersIds();
        $signetCounts = array_combine($playersIds, array_map(fn($id) => $this->tokenManager->getSignetCount($this->getPlayerPosition($id)), $playersIds));
        $winner = null;

        //filter players with at least 5 signets
        $playersWith5Signets = array_filter($signetCounts, fn($count) => $count >= 5);
        //$this->dump('*******************playersWith5Signets', $playersWith5Signets);

        //check if several players have the maximum signets count
        $maxSignetsCount = max($signetCounts);
        $playersWithMaxSignets = array_filter($signetCounts, fn($count) => $count == $maxSignetsCount);
        //$this->dump('*******************playersWithMaxSignets', $playersWithMaxSignets);

        //check if every player from playersWith5Signets has the same signets count
        $tieOn5SignetsOrMore = count($playersWithMaxSignets) > 1;
        // $this->dump('*******************tieOn5SignetsOrMore', $tieOn5SignetsOrMore);
        if (!$playersWith5Signets || $tieOn5SignetsOrMore) {
            //no winner yet
        } else {
            if (count($playersWith5Signets) == 1) {
                //if only player has 5 signets, he wins
                $winner = array_key_first($playersWith5Signets);
            } else {
                //winner is the player with the most signets
                $winners = array_keys(array_filter($playersWith5Signets, fn($count) => $count == $maxSignetsCount));
                $winner = $winners[0];
            }
        }

        //$this->dump('*******************signetCountWinner', $winner);
        return $winner;
    }

    /**
     * Returns id of the only one player who has cards or who has most signets in case of tie
     * return -1 if tie on no card and signets count
     * return null if everyone has cards
     * @param bool $checkOnlyHand if true (mostly for scrapyard), only checks cards in hand for immediate check if playing is possible. Can’t check deck too because a card just got back to the deck. Otherwise checks cards in deck and hand
     * @return int|null 
     */
    function getNoPlayableCardWinner(bool $checkOnlyHand = false): int|null {
        $winner = null;
        $playersIds = $this->getPlayersIds();

        //if no card in hand and no card in deck, won’t be able to play
        if ($checkOnlyHand) {
            $cardsCount = array_combine($playersIds, array_map(
                fn($id) => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($id), MATERIAL_LOCATION_HAND),
                $playersIds
            ));
        } else {
            $cardsCount = array_combine($playersIds, array_map(
                fn($id) => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($id), MATERIAL_LOCATION_DECK)
                    + $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($id), MATERIAL_LOCATION_HAND),
                $playersIds
            ));
        }

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
            //$this->dump('*******************check on signets, winners', $winners);
            if (count($winners) == 1) {
                $winner = $winners[0];
            } else {
                $winner = -1;
            }
        }
        return $winner;
    }

    function stEndOfRound() {
        if ($this->hasReachedEndOfGameRequirements()) {
            $this->gamestate->nextState('nextRound');
        } else {
            $this->gamestate->nextState('seeEndOfRound');
        }
    }

    function stNextRound() {
        $players = $this->loadPlayersBasicInfos();
        $currentRound = $this->globals->get(GLB_ROUND);
        foreach ($players as $playerId => $player) {
            $count = $this->tokenManager->getSignetCount($player["player_no"]);
            $this->setStat($count, "game_signets_round_$currentRound", $playerId);
        }

        if ($this->hasReachedEndOfGameRequirements()) {
            if ($this->isStudio()) {
                $this->gamestate->nextState('debugEndGame');
            } else {
                $this->gamestate->nextState('endGame');
            }
        } else {
            $this->endOfRoundReset();
            $currentRound++;

            self::notifyAllPlayers('newRound', clienttranslate('&#10148; Round ${round}'), ["round" => $currentRound]);
            $this->cardManager->resetDecks();
            $this->tokenManager->resetTokens();
            $this->notifyCounterChange();
            $this->gamestate->nextState('chooseDuelCard');
        }
    }

    function endOfRoundReset() {
        $this->globals->set(GLB_DUEL_COUNT, 1);
        $this->globals->inc(GLB_ROUND, 1);
        $this->globals->set(GLB_SERPENT_TEMPLE_DISCARDERS, []);
        $this->globals->delete(GLB_ROUND_WINNERS);
        $this->globals->delete(GLB_ROUND_EVERYONE_LOOSES);
        $this->globals->delete(GLB_ABILITY_TO_RESOLVE);
        $this->globals->delete(GLB_ABILITY_TO_RESOLVE_COPIED_TYPE);

        foreach ($this->getPlayersIds() as $playerId) {
            $this->globals->delete(GLB_NEXT_CARD_MODIFIER . $playerId);
            $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL . $playerId);
            $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL_COUNTDOWN . $playerId);
            $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED . $playerId);
            $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED_COUNTDOWN . $playerId);
            $this->globals->delete(GLB_LAST_CHOSEN_CARD . $playerId);
            $this->globals->delete(GLB_BLACKMAILER_FACTION . $playerId);
            $this->globals->delete(GLB_BLACKMAILER_COUNTDOWN . $playerId);
        }
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
}

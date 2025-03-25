/**
 * Your game interfaces
 */
declare const define
declare const ebg
declare const $
declare const dojo: Dojo
declare var g_replayFrom: number | undefined
declare var g_gamethemeurl: string
declare var g_themeurl: string
declare var g_archive_mode: string
declare function _(str: string): string

type Faction = 'G' | 'Y' | 'R' | 'B'
type TokenType = 'S' | 'O'
type PowerType = 'I' | 'O'

// remove this if you don't use cards. If you do, make sure the types are correct . By default, some number are send as string, I suggest to cast to right type in PHP.
interface Card {
	id: number
	location: string
	location_arg: number
	type: number
	type_arg: number
}
interface CardiaCard extends Card {}

interface CardiaPlayer extends Player {
	playerNo: number
	cardsCount: number
	hand: CardiaCard[]
}

interface CardiaGamedatas {
	current_player_id: string
	decision: { decision_type: string }
	game_result_neutralized: string
	gamestate: Gamestate
	gamestates: { [gamestateId: number]: Gamestate }
	neutralized_player_id: string
	notifications: { last_packet_id: string; move_nbr: string }
	playerorder: (string | number)[]
	playerOrderWorkingWithSpectators: number[] //starting with current player
	players: { [playerId: number]: CardiaPlayer }
	tablespeed: string
	lastTurn: boolean
	turnOrderClockwise: boolean
	expansion: number
	// counters
	scores?: Array<NotifScoreArgs>
	winners: number[]
	version: string
	counters: Map<string, CounterValue>
	// Add here variables you set up in getAllDatas
}

interface CounterValue {
	counter_name: string
	counter_value: number
}

interface CardiaGame extends Game {
	cardsManager: CardsManager
	animationManager: AnimationManager
	getZoom(): number
	getCurrentPlayer(): CardiaPlayer
	getPlayerId(): number
	getPlayerScore(playerId: number): number
	setTooltip(id: string, html: string): void
	setTooltipToClass(className: string, html: string): void
	clientActionData: ClientActionData
	resetClientActionData(): void
	slide(mobileElt, targetElt, options)
	addTooltipOnClickHelpButton(idButton: string, tooltipContent: string, delay?: number): void
}

interface EnteringChooseActionArgs {
	canPass: boolean
	canResetTurn: boolean
}

interface NotifPointsArgs {
	playerId: number
	points: number
	delta: number
	scoreType: string
}

interface NotifScoreArgs {
	playerId: number
	score: number
	scoreType: string
}

interface NotifCounter {
	counterName: string
	counterValue: number
	playerId: number
}

interface NotifUpdateCounters {
	counters: []
}

interface NotifWinnerArgs {
	playerId: number
}

interface NotifScorePointArgs {
	playerId: number
	points: number
}

interface NotifImportantMessageArgs {
	message: string
	type: 'POSITIVE' | 'NEGATIVE' | 'WARNING'
	temporary: boolean
}

type MoveLocation = 'HAND' | 'DECK' | 'STOCK' | 'TABLE' | 'DISCARD'

interface NotifMaterialMove {
	type: 'CARD' | 'TOKEN' | 'FIRST_PLAYER_TOKEN'
	from: MoveLocation
	to: MoveLocation
	fromArg: number
	toArg: number
	material: Array<any | string> //elements (cards for exemple), or tokenIds
}

interface SwappedMaterial {
	from: 'HAND' | 'DECK' | 'FESTIVAL'
	to: 'HAND' | 'DECK' | 'FESTIVAL'
	fromArg: number
	toArg: number
	material: any | string
}

type MaterialType = 'CARD' | 'TOKEN' | 'FIRST_PLAYER_TOKEN'

interface NotifMaterialSwap {
	type: MaterialType
	material1: SwappedMaterial
	material2: SwappedMaterial
}

interface ClientActionData {
	placedCardId: string
	destinationSquare: string
	previousCardParentInHand: HTMLElement
}

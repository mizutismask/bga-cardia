/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Cardia implementation : © Séverine Kamycki <mizutismask@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * cardia.ts
 *
 * Cardia user interface script
 *
 * In this file, you are describing the logic of your user interface, in Typescript language.
 *
 */
declare const playSound

const IMAGE_ITEMS_PER_ROW = 4
const IMAGE_LOCATIONS_PER_ROW = 4
const ACTION_TIMER_DURATION = 6
const TOKEN_MOVE_DURATION = 500
const ANIMATED_TOKEN_Z_INDEX = '50'

const HIRED_BLADE = 101
const VOID_MAGE = 102
const SURGEON = 103
const MEDIATOR = 104
const SABOTEUR = 105
const FORTUNE_TELLER = 106
const PALACE_GUARD = 107
const JUDGE = 108
const AMBUSHER = 109
const PUPPETEER = 110
const CLOCKMAKER = 111
const TREASURER = 112
const SWAMP_GUARDIAN = 113
const MAGISTRA = 114
const INVENTOR = 115
const DJINN = 116

const POISONER = 201
const KINESIS_MAGE = 202
const ENVOY = 203
const TAX_COLLECTOR = 204
const REVOLUTIONARY = 205
const LIBRARIAN = 206
const PRODIGY = 207
const ARISTOCRAT = 208
const BLACKMAILER = 209
const ILLUSIONIST = 210
const ENGINEER = 211
const COUNSELOR = 212
const WITCH_KING = 213
const ELEMENTAL = 214
const MECHANICAL_DJINN = 215
const SUCCESSOR = 216

class Cardia extends BaseGame implements CardiaGame {
	public cardsManager: CardsManager
	private originalTextChooseAction: string

	private ticketsCounters: Counter[] = []
	private handCardsCounters: Counter[] = []
	private centralZone: CentralZone
	public discards: LineStock<CardiaCard>[] = []

	protected settings = [new Setting('customSounds', 'pref', 1)]
	private displayedTooltip

	/*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
	public setup(gamedatas: any) {
		log('Starting game setup')
		this.dontPreloadUselessAssets()

		this.includeHtmlBasicTemplate()
		this.gameFeatures = new GameFeatureConfig()
		this.gamedatas = gamedatas
		log('gamedatas', gamedatas)

		this.cardsManager = new CardsManager(this, this.gamedatas.expansion)
		this.animationManager = new AnimationManager(this)

		this.centralZone = new CentralZone(this, this.gamedatas.duels)

		if (gamedatas.lastTurn) {
			this.notif_lastTurn(false)
		}

		Object.values(this.gamedatas.playerOrderWorkingWithSpectators).forEach((p) => {
			this.setupPlayer(this.gamedatas.players[p], p == this.getPlayerId() ? this.gamedatas.hand : [])
		})
		this.safeUpdateCounters(this.gamedatas.counters)

		$('overall-content').classList.add(`player-count-${this.getPlayersCount()}`)

		this.setupDiscards()
		this.setupPreferences()
		this.setupTooltips()
		this.setupHelpPopin()
		this.setupLocation()
		this.setupData()
		this.setupNotifications()

		log('Ending game setup')
	}

	public setupDiscards() {
		dojo.place(`<div id="discards-wrapper"></div>`, `custom-game-area`)

		const players = this.getPlayersInOrder()
		players.forEach((player) => {
			const html = `
			<div class="cst-block">
				<div id="discard-${player.id}"></div>
				<div class="zone-title"><span class="player-name" style="color:#${player.color}">${this.format_string_recursive(
				_('${player_name}’s discard'),
				{ player_name: player.name }
			)}</span></div>
			</div>
        `
			dojo.place(html, `discards-wrapper`)

			this.discards[player.id] = new LineStock<CardiaCard>(this.cardsManager, $('discard-' + player.id))
			this.discards[player.id].setSelectionMode('none')
			this.discards[player.id].addCards(player.discard)
		})
	}

	public setupLocation() {
		if (!this.isTouch && this.gamedatas.location != 0) {
			dojo.place(
				`<div class='player_board_location location-desktop location-${
					this.gamedatas.location
				}' id="player_board_location" style="${getBackgroundInlineStyleForLocation(this.gamedatas.location)}">`,
				`location-area`,
				'first'
			)
			/*
				if (this.isTouch) { 
								dojo.place($(`player_board_location`), `discards-wrapper`, 'first')
							}

			*/
			this.setTooltip(
				'player_board_location',
				this.gamedatas.locationOptions[this.gamedatas.location].description
			)
		}
	}

	private setupTooltips() {
		//todo change counter names
		this.setTooltipToClass('revealed-tokens-back-counter', _('counter1 tooltip'))
		this.setTooltipToClass('tickets-counter', _('counter2 tooltip'))
		this.setTooltipToClass('hand-cards-counter', _('Cards in hand'))
		this.setTooltipToClass('deck-cards-counter', _('Cards in deck'))
		this.setTooltipToClass('signets-counter', _('Won signets'))

		this.setTooltipToClass('cstm-help-icon', `<div class="help-card recto"></div>`)
		this.setTooltipToClass('cstm-help-icon-mini', `<div class="help-card verso"></div>`)
		this.setTooltipToClass('player-turn-order', _('First player'))
	}

	private setupPlayer(player: CardiaPlayer, handCards: CardiaCard[] = []) {
		document.getElementById(`overall_player_board_${player.id}`).dataset.playerColor = player.color
		if (this.gameFeatures.showPlayerOrderHints) {
			this.setupPlayerOrderHints(player)
		}
		this.setupMiniPlayerBoard(player)
		this.playerTables[player.id] = new PlayerTable(this, player, handCards)
	}

	private setupMiniPlayerBoard(player: CardiaPlayer) {
		const playerId = Number(player.id)
		dojo.place(
			`<div id="counters-${player.id}" class="counters">
				<div id="signets-counter-${player.id}-wrapper" class="counter signets-counter">
					<div class="icon signet-icon"></div> 
					<span id="signets-counter-${player.id}"></span>
				</div>
			
				<div id="deck-cards-counter-${player.id}-wrapper" class="counter deck-cards-counter counter-left-part">
					<div class="deck-icon">
						<svg version="1.0" xmlns="http://www.w3.org/2000/svg"
						width="662.000000pt" height="782.000000pt" viewBox="0 0 662.000000 782.000000"
						preserveAspectRatio="xMidYMid meet">

						<g transform="translate(0.000000,782.000000) scale(0.100000,-0.100000)"
						fill="currentColor" stroke="none">
						<path d="M1815 7776 c-235 -54 -427 -216 -505 -423 -12 -32 -88 -326 -170
						-653 -82 -327 -358 -1434 -615 -2460 -509 -2035 -493 -1960 -461 -2120 33
						-160 149 -328 279 -404 38 -22 104 -98 324 -374 152 -190 295 -363 317 -384
						57 -55 164 -125 237 -154 56 -23 162 -50 579 -149 80 -19 305 -73 500 -120
						195 -46 411 -98 480 -115 178 -42 456 -109 740 -179 485 -119 746 -180 815
						-192 81 -14 265 -6 340 14 191 53 352 168 459 331 103 157 68 36 426 1461 792
						3152 1019 4066 1025 4121 15 130 -28 275 -114 385 -56 71 -142 140 -666 537
						-126 96 -241 185 -255 198 -45 42 -184 119 -255 141 -40 13 -748 136 -1630
						284 -1664 278 -1717 286 -1850 255z m367 -221 c79 -13 411 -69 738 -124 327
						-55 728 -123 890 -150 162 -28 538 -91 835 -141 297 -50 558 -96 580 -101 111
						-30 204 -111 257 -224 29 -63 32 -79 32 -160 -1 -81 -11 -132 -107 -505 -150
						-588 -328 -1295 -787 -3125 -221 -880 -410 -1619 -420 -1643 -11 -23 -37 -69
						-60 -100 -105 -151 -267 -208 -471 -168 -70 14 -552 125 -1054 241 -60 15
						-337 78 -615 141 -565 129 -845 193 -1000 229 -58 14 -163 38 -235 55 -245 57
						-339 103 -413 202 -70 92 -111 225 -97 313 10 64 797 3226 1072 4305 58 228
						116 462 130 520 57 242 165 379 348 442 73 25 206 23 377 -7z m3588 -922 c25
						-82 25 -155 1 -274 -11 -52 -135 -551 -275 -1109 -141 -558 -425 -1691 -631
						-2517 -206 -826 -384 -1527 -395 -1557 -50 -133 -158 -254 -281 -315 -104 -53
						-169 -65 -304 -58 -110 6 -138 11 -600 121 -104 24 -392 92 -640 151 -247 58
						-495 117 -550 130 -55 13 -181 43 -280 65 -99 22 -254 58 -345 80 -91 22 -262
						62 -381 89 -234 54 -265 64 -341 113 -75 49 -41 47 195 -7 111 -25 290 -66
						397 -91 367 -84 783 -180 865 -199 33 -8 283 -67 555 -131 272 -63 576 -136
						675 -161 154 -39 199 -46 310 -50 101 -4 144 -1 195 12 202 51 365 196 448
						398 17 41 143 519 291 1107 143 569 393 1562 555 2205 162 644 336 1334 387
						1535 85 336 92 373 93 468 1 99 2 102 18 81 10 -11 27 -50 38 -86z m205 -143
						c42 -108 36 -182 -36 -448 -61 -221 -732 -2895 -1029 -4097 -206 -836 -237
						-949 -281 -1041 -70 -148 -203 -262 -370 -319 -70 -23 -101 -28 -189 -28 -119
						0 -200 14 -548 99 -487 118 -659 159 -1342 319 -58 14 -161 38 -230 54 -69 17
						-224 53 -345 81 -368 85 -548 131 -585 150 -35 19 -108 70 -99 70 28 0 298
						-60 984 -220 818 -191 1201 -282 1580 -375 449 -111 558 -112 768 -7 76 37
						107 61 177 132 101 102 158 201 200 348 42 145 1255 5001 1279 5119 12 55 21
						134 21 174 0 85 8 83 45 -11z m207 -174 c37 -121 42 -94 -228 -1151 -129 -505
						-1011 -4033 -1047 -4190 -58 -249 -114 -361 -237 -479 -162 -155 -375 -221
						-585 -181 -49 9 -220 48 -380 86 -159 38 -337 81 -395 95 -58 14 -148 36 -200
						49 -52 13 -221 53 -375 89 -154 36 -381 90 -505 120 -124 30 -361 87 -528 126
						-334 79 -427 107 -487 148 -34 23 -37 27 -19 30 23 3 264 -49 604 -131 209
						-51 520 -125 955 -227 99 -23 385 -92 635 -152 582 -140 573 -138 694 -138
						356 0 638 231 739 605 15 55 265 1054 556 2220 291 1166 580 2318 641 2560
						100 392 112 450 114 532 1 108 12 106 48 -11z m195 -113 c43 -98 42 -153 -10
						-348 -25 -93 -112 -433 -192 -755 -80 -322 -171 -682 -201 -800 -30 -118 -172
						-681 -315 -1250 -485 -1930 -600 -2381 -620 -2435 -28 -76 -92 -170 -157 -232
						-73 -71 -213 -141 -297 -149 l-60 -6 80 46 c102 60 199 138 248 201 21 28 60
						95 87 150 48 96 63 151 406 1520 195 781 477 1904 626 2495 148 591 290 1156
						315 1255 28 116 46 213 50 273 3 50 8 92 10 92 2 0 16 -26 30 -57z"/>
						<path d="M1950 7266 c-30 -7 -70 -20 -88 -30 -46 -24 -107 -89 -131 -139 -23
						-47 -23 -48 -425 -1647 -438 -1746 -631 -2511 -702 -2788 -38 -153 -64 -274
						-64 -306 0 -72 24 -137 72 -191 72 -82 89 -88 524 -180 220 -46 597 -127 839
						-180 242 -53 521 -113 620 -135 99 -21 374 -82 612 -134 499 -111 533 -114
						631 -65 67 33 105 72 133 135 10 22 257 994 549 2160 291 1165 564 2252 606
						2414 83 321 86 357 45 437 -29 58 -109 126 -173 147 -29 9 -330 64 -668 122
						-338 57 -718 122 -845 144 -126 22 -322 55 -435 74 -113 19 -243 42 -290 50
						-731 128 -736 129 -810 112z m465 -181 c176 -30 583 -100 905 -154 322 -55
						745 -127 940 -161 195 -34 423 -73 505 -87 83 -14 171 -34 198 -45 86 -37 125
						-111 108 -204 -11 -54 -1049 -4199 -1131 -4514 -58 -226 -86 -291 -139 -326
						-78 -51 -84 -51 -846 121 -255 58 -1382 304 -1878 411 -164 35 -307 68 -316
						74 -34 17 -80 80 -91 121 -6 23 -8 60 -5 83 6 41 413 1674 670 2691 74 292
						197 780 274 1085 173 684 207 813 225 848 24 46 89 101 131 111 52 13 72 10
						450 -54z"/>
						</g>
						</svg>
					</div> 
					<span id="deck-cards-counter-${player.id}"></span>
				</div>
				<div id="hand-cards-counter-${player.id}-wrapper" class="counter hand-cards-counter counter-left-part">
					<div class="fa fa-2x fa-hand-paper-o"></div> 
					<span id="hand-cards-counter-${player.id}"></span>
				</div>
			</div>
			<div id="additional-info-${player.id}" class="counters additional-info">
				<div id="additional-icons-${player.id}" class="additional-icons"></div> 
			</div>
			`,
			`player_board_${player.id}`
		)

		/* const revealedTokensBackCounter = new ebg.counter();
            revealedTokensBackCounter.create(`revealed-tokens-back-counter-${player.id}`);
            revealedTokensBackCounter.setValue(player.revealedTokensBackCount);
            this.revealedTokensBackCounters[playerId] = revealedTokensBackCounter;

            const ticketsCounter = new ebg.counter();
            ticketsCounter.create(`tickets-counter-${player.id}`);
            ticketsCounter.setValue(player.ticketsCount);
            this.ticketsCounters[playerId] = ticketsCounter;*/

		const cardsCounter = new ebg.counter()
		cardsCounter.create(`hand-cards-counter-${player.id}`)
		cardsCounter.setValue(player.cardsCount)
		this.handCardsCounters[playerId] = cardsCounter

		if (this.gameFeatures.showPlayerHelp && this.getPlayerId() === playerId) {
			//help
			dojo.place(`<div id="player-help" class="css-icon cstm-help-icon">?</div>`, `additional-icons-${player.id}`)
		}

		if (this.gameFeatures.showFirstPlayer && player.playerNo === 1) {
			dojo.place(
				`<div id="firstPlayerIcon" class="css-icon player-turn-order">1<span class="exponent">st<span></div>`,
				`additional-icons-${player.id}`,
				`last`
			)
		}

		if (this.gameFeatures.spyOnOtherPlayerBoard && this.getPlayerId() !== playerId) {
			//spy on other player
			dojo.place(
				`
            <div class="show-player-tableau"><a href="#anchor-player-${player.id}" classes="inherit-color">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 85.333343 145.79321">
                    <path fill="currentColor" d="M 1.6,144.19321 C 0.72,143.31321 0,141.90343 0,141.06039 0,140.21734 5.019,125.35234 11.15333,108.02704 L 22.30665,76.526514 14.626511,68.826524 C 8.70498,62.889705 6.45637,59.468243 4.80652,53.884537 0.057,37.810464 3.28288,23.775161 14.266011,12.727735 23.2699,3.6711383 31.24961,0.09115725 42.633001,0.00129225 c 15.633879,-0.123414 29.7242,8.60107205 36.66277,22.70098475 8.00349,16.263927 4.02641,36.419057 -9.54327,48.363567 l -6.09937,5.36888 10.8401,30.526466 c 5.96206,16.78955 10.84011,32.03102 10.84011,33.86992 0,1.8389 -0.94908,3.70766 -2.10905,4.15278 -1.15998,0.44513 -19.63998,0.80932 -41.06667,0.80932 -28.52259,0 -39.386191,-0.42858 -40.557621,-1.6 z M 58.000011,54.483815 c 3.66666,-1.775301 9.06666,-5.706124 11.99999,-8.735161 l 5.33334,-5.507342 -6.66667,-6.09345 C 59.791321,26.035633 53.218971,23.191944 43.2618,23.15582 33.50202,23.12041 24.44122,27.164681 16.83985,34.94919 c -4.926849,5.045548 -5.023849,5.323672 -2.956989,8.478106 3.741259,5.709878 15.032709,12.667218 24.11715,14.860013 4.67992,1.129637 13.130429,-0.477436 20,-3.803494 z m -22.33337,-2.130758 c -2.8907,-1.683676 -6.3333,-8.148479 -6.3333,-11.893186 0,-11.58942 14.57544,-17.629692 22.76923,-9.435897 8.41012,8.410121 2.7035,22.821681 -9,22.728685 -2.80641,-0.0223 -6.15258,-0.652121 -7.43593,-1.399602 z m 14.6667,-6.075289 c 3.72801,-4.100734 3.78941,-7.121364 0.23656,-11.638085 -2.025061,-2.574448 -3.9845,-3.513145 -7.33333,-3.513145 -10.93129,0 -13.70837,13.126529 -3.90323,18.44946 3.50764,1.904196 7.30574,0.765377 11,-3.29823 z m -11.36999,0.106494 c -3.74071,-2.620092 -4.07008,-7.297494 -0.44716,-6.350078 3.2022,0.837394 4.87543,-1.760912 2.76868,-4.29939 -1.34051,-1.615208 -1.02878,-1.94159 1.85447,-1.94159 4.67573,0 8.31873,5.36324 6.2582,9.213366 -1.21644,2.27295 -5.30653,5.453301 -7.0132,5.453301 -0.25171,0 -1.79115,-0.934022 -3.42099,-2.075605 z"></path>
                </svg>
                </a>
            </div>
            `,
				`additional-icons-${player.id}`
			)
		}
	}

	private setupHelpPopin() {
		const buttons: BgaHelpButton[] = [
			new BgaHelpPopinButton({
				title: '',
				html: this.getHelpHtml(),
				buttonBackground: 'white',
				buttonColor: '#266059'
			})
		]

		if (this.gamedatas.location != 0 && this.isTouch) {
			buttons.push(
				new BgaHelpExpandableButton({
					unfoldedHtml: `<div id="player-help-location-wrapper" class="player_board_location" style="${getBackgroundInlineStyleForLocation(
						this.gamedatas.location
					)}">
											</div>
											<div style="min-width:240px">${this.gamedatas.locationOptions[this.gamedatas.location].description}</div>
									`,
					expandedWidth: '240px',
					expandedRadius: '3%',
					foldedContentExtraClasses: 'bga-help_popin-button',
					foldedHtml: '💀',
					buttonExtraClasses: 'location-help-button'
				})
			)
		}

		new HelpManager(this, { buttons: buttons })
	}

	private getHelpHtml() {
		let html = `
        <div id="help-popin"> 
			<div id="help-1" class="popin-help-card"></div>
			<div id="help-2" class="popin-help-card"></div>
		`
		/*new Set(this.gamedatas.rolesInPlay).forEach((r) => {
			html += this.getRoleHtml(r, this.gamedatas.rolesInPlay.filter((allR) => allR === r).length)
		})*/
		html += `
        </div>
        `
		return html
	}

	public updateCustomCounters(counters) {
		Object.keys(counters).forEach((counterId) => {
			const counterValue: CounterValue = counters[counterId]
			if (counterValue.counter_name.includes('cards-counter')) {
				const location = getPart(counterValue.counter_name, 0, true)
				const player = getPart(counterValue.counter_name, -1, true)

				switch (location) {
					case 'discard':
						//this.playerTables[Number(player)]?.discard.setCardNumber(counterValue.counter_value)
						break

					default:
						break
				}
			}
		})
	}

	private updateModifierOnCard(cardId: number, modifier: number) {
		this.updateModifierOnElement($(`cardia-card-${cardId}-modifier-value`), modifier)
	}
	private updateModifierOnLastSlot(div: HTMLElement, modifier: number, playerId: number) {
		const modifierId = `temp-${playerId}-modifier-value`
		dojo.place(`<div id="${modifierId}" class="temp-modifier"></div>`, div)
		this.updateModifierOnElement($(modifierId), modifier)
	}

	private updateModifierOnElement(element: HTMLElement, modifier: number) {
		if (element) {
			element.innerHTML = `${modifier > 0 ? '+' + modifier : modifier}`
			element.dataset.value = `${modifier}`
			element.classList.remove('positive-modifier', 'negative-modifier')
			element.classList.add('modifier')
			element.classList.add(modifier > 0 ? 'positive-modifier' : 'negative-modifier')
		}
	}

	private setupData() {
		this.gamedatas.signets.forEach((s) => this.createSignetOnCard(s))
		this.gamedatas.ongoingTokens.forEach((s) => this.createOnGoingTokenOnCard(s))
		this.updateModifiers(this.gamedatas.modifiers)

		Object.values(this.gamedatas.players).forEach((p) => {
			if (p.nextCardModifier) {
				this.updateNextCardModifier(Number(p.id), p.playerNo, p.nextCardModifier)
			}
		})
	}

	private updateModifiers(modifiers: { [cardId: number]: number }) {
		Object.entries(modifiers).forEach(([cardId, modifier]) => {
			this.updateModifierOnCard(Number(cardId), modifier)
		})
	}

	private createSignetOnCard(signet: Token) {
		this.createSignetOnElement(signet.id, signet.location_arg)
	}

	private createSignetOnElement(signetId: number, cardId: number) {
		const signetDivId = `signet-${signetId}`
		const location = `cardia-card-${cardId}-signets`
		if ($(signetDivId) && $(location)) {
			this.animationManager.attachWithAnimation(
				new BgaSlideAnimation({
					element: $(signetDivId),
					zoom: 1
				}),
				$(location)
			)
		} else if ($(location)) {
			dojo.place(`<div id="${signetDivId}" class="signet-icon"></div>`, 'void-area')
			document.getElementById(`cardia-card-${cardId}`).style.zIndex = ANIMATED_TOKEN_Z_INDEX
			this.animationManager
				.attachWithAnimation(
					new BgaSlideAnimation({
						element: $(signetDivId),
						duration: TOKEN_MOVE_DURATION
					}),
					$(location)
				)
				.then(() => {
					document.getElementById(`cardia-card-${cardId}`).style.zIndex = '0'
					//this.animationManager.play(new BgaPauseAnimation({}))
				})
		} else {
			console.error('can’t put signet on ' + location)
		}
	}

	private createOnGoingTokenOnCard(signet: Token) {
		this.createOnGoingTokenOnElement(signet.id, signet.location_arg)
	}

	private createOnGoingTokenOnElement(tokenId: number, cardId: number) {
		const tokenDivId = `ongoing-${tokenId}`
		const location = `cardia-card-${cardId}-ongoings`
		if ($(tokenDivId) && $(location)) {
			this.animationManager.attachWithAnimation(
				new BgaSlideAnimation({
					element: $(tokenDivId),
					zoom: 1
				}),
				$(location)
			)
		} else if ($(location)) {
			dojo.place(`<div id="${tokenDivId}" class="ongoing-icon"></div>`, 'void-area')
			document.getElementById(`cardia-card-${cardId}`).style.zIndex = ANIMATED_TOKEN_Z_INDEX
			this.animationManager
				.attachWithAnimation(
					new BgaSlideAnimation({
						element: $(tokenDivId),
						duration: TOKEN_MOVE_DURATION
					}),
					$(location)
				)
				.then(() => {
					document.getElementById(`cardia-card-${cardId}`).style.zIndex = '0'
					//this.animationManager.play(new BgaPauseAnimation({}))
				})
		} else {
			console.error('can’t put ongoing token on ' + location)
		}
	}

	private removeSignetOnCard(signet: Token) {
		const divId = `signet-${signet.id}`
		dojo.destroy(divId)
	}

	private removeOnGoingTokenOnCard(signet: Token) {
		const divId = `ongoing-${signet.id}`
		dojo.destroy(divId)
	}

	///////////////////////////////////////////////////
	//// Game & client states

	// onEnteringState: this method is called each time we are entering into a new game state.
	//                  You can use this method to perform some user interface changes at this moment.
	//
	public onEnteringState(stateName: string, args: any) {
		log('Entering state: ' + stateName, args)
		removeClass('discard-purpose')

		switch (stateName) {
			case 'chooseAction':
				if (args?.args) {
					const dataArgs = args.args as EnteringChooseActionArgs
					this.onEnteringChooseAction(dataArgs)
				}
				break
			case 'interactiveAbility':
			case 'interactiveAbilityStep2':
				if (args?.args) {
					const dataArgs = args.args as EnteringInteractiveAbilityArgs
					this.onEnteringInteractiveAbility(dataArgs)
				}
				break
			case 'chooseDuelCard':
				const dataArgs = args.args as EnteringChooseDuelCardArgs
				this.onEnteringChooseDuelCard(dataArgs)
				break
			case 'stDuelReveal':
				//this.centralZone.createDuelStock(null, null)
				break
			case 'scrapyardChooseCard':
				this.onEnteringScrapyardChooseCard()
				break
			case 'blackmailerDiscard':
				this.onEnteringBlackmailerDiscard()
				break
			case 'serpentTempleDiscard':
				this.setHandAsSelectableArea()
				break
		}
		if (this.gameFeatures.spyOnActivePlayerInGeneralActions) {
			this.addArrowsToActivePlayer(args)
		}
	}

	private onEnteringChooseAction(args: EnteringChooseActionArgs) {
		//todo
		if ((this as any).isCurrentPlayerActive()) {
			this.resetClientActionData()
			const actions = this.getPossibleActions(args)
			this.setChooseActionGamestateDescription(actions.join(_(' or ')))
		}
		//this.missions.addCards(args._private.missions).then(()=>this.missions.setSelectableCards(args._private.choosableMissions))
	}

	private onEnteringChooseDuelCard(dataArgs: EnteringChooseDuelCardArgs) {
		if (dataArgs?._private?.blackmailerFaction) {
			this.setGamestateDescription('BlackmailerAbility')
		}
		this.setHandAsSelectableArea()
	}

	private onEnteringScrapyardChooseCard() {
		this.setHandAsSelectableArea()
		this.playerTables[this.getPlayerId()]?.handStock.getCards().forEach((card: CardiaCard) => {
			this.cardsManager.getCardElement(card).classList.add('discard-purpose')
		})
	}

	private onEnteringBlackmailerDiscard() {
		this.setHandAsSelectableArea()
		this.playerTables[this.getPlayerId()]?.handStock.setSelectionMode('multiple')
		this.playerTables[this.getPlayerId()]?.handStock.getCards().forEach((card: CardiaCard) => {
			this.cardsManager.getCardElement(card).classList.add('discard-purpose')
		})
	}

	private setHandAsSelectableArea() {
		this.centralZone.duelStocks.forEach((stock) => {
			stock.setSelectionMode('none')
		})
		this.playerTables[this.getPlayerId()]?.handStock.setSelectionMode('single')
	}

	private onEnteringInteractiveAbility(args: EnteringInteractiveAbilityArgs) {
		this.setGamestateDescription(args.abilityCard.type.toString())
		if ((this as any).isCurrentPlayerActive()) {
			if (args.interactionType === 'selectCardFromDuels') {
				/*if (args.prompt) {
				this.statusBar.setTitle(args.prompt, args)
			}*/
				//selection on central zone instead of hand
				this.centralZone.duelStocks.forEach((stock) => {
					stock.setSelectionMode('single')
				})
				this.playerTables[this.getPlayerId()]?.handStock.setSelectionMode('none')
				if (args.selectableCards) {
					this.playerTables[this.getPlayerId()]?.handStock.setSelectableCards(args['selectableCards'])
				}
			} else if (args.interactionType === 'selectCardFromHand') {
				this.centralZone.duelStocks.forEach((stock) => {
					stock.setSelectionMode('none')
				})
				this.playerTables[this.getPlayerId()]?.handStock.setSelectionMode(args.qty > 1 ? 'multiple' : 'single')
				if (args.selectableCards) {
					this.playerTables[this.getPlayerId()]?.handStock.setSelectableCards(args.selectableCards)
					args.selectableCards.forEach((card: CardiaCard) => {
						this.cardsManager.getCardElement(card).classList.add('discard-purpose')
					})
				}
			}
		}
	}

	private getPossibleActions(args: EnteringChooseActionArgs) {
		const actions = []

		//if (args.canBuild) actions.push(_('Build your mall'))
		//if (args.canTakeMoney) actions.push(_('Take money from the dispenser'))

		if (actions.length === 0) {
			actions.push(_('No possible action left'))
		}
		return actions
	}

	// onLeavingState: this method is called each time we are leaving a game state.
	//                 You can use this method to perform some user interface changes at this moment.
	//
	public onLeavingState(stateName: string) {
		log('Leaving state: ' + stateName)

		switch (stateName) {
			case 'interactiveAbility':
			case 'interactiveAbilityStep2':
				this.centralZone.duelStocks.forEach((stock) => stock.unselectAll())
				break
		}
	}

	// onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
	//                        action status bar (ie: the HTML links in the status bar).
	//
	public onUpdateActionButtons(stateName: string, args: any) {
		log('onUpdateActionButtons: ' + stateName, args)
		if ((this as any).isCurrentPlayerActive()) {
			switch (stateName) {
				case 'seeEndOfRound':
					;(this as any).addActionButton('pass-button', _('Pass'), () => this.pass())
					break
				case 'chooseDuelCard':
				case 'chooseFortuneTellerCard':
					this.statusBar.addActionButton(
						_('Validate'),
						() => this.chooseDuelCardAction(this.playerTables[this.getPlayerId()].handStock),
						{}
					)
					//this.setActionBarChooseAction(false)
					break
				case 'scrapyardChooseCard':
					this.statusBar.addActionButton(
						_('Validate'),
						() => this.chooseScrapyardCardAction(this.playerTables[this.getPlayerId()].handStock),
						{}
					)
					//this.setActionBarChooseAction(false)
					break
				case 'serpentTempleDiscard':
					this.statusBar.addActionButton(
						_('Validate'),
						() => this.serpentTempleDiscardAction(this.playerTables[this.getPlayerId()].handStock),
						{}
					)
					break
				case 'interactiveAbility':
				case 'interactiveAbilityStep2':
					const typedArgs = args as EnteringInteractiveAbilityArgs
					if (typedArgs.interactionType === 'selectFaction') {
						;['G', 'Y', 'R', 'B'].forEach((faction) => {
							this.addImageActionButton(
								`select_faction_button_${faction}`,
								createDiv(`faction faction-${faction}`, `faction-${faction}-content`),
								'primary',
								'',
								() => {
									this.selectFaction(faction)
								}
							)
						})
					} else if (typedArgs.interactionType === 'selectCardFromDuels') {
						if (typedArgs.abilityCard.type == VOID_MAGE) {
							this.statusBar.addActionButton(
								_('Remove modifiers'),
								() =>
									this.selectCardAction(
										stateName,
										typedArgs.optionalSelection,
										this.getSelectedDuelStock(),
										{ option: 'removeModifiers', qty: typedArgs.qty }
									),
								{}
							)
							this.statusBar.addActionButton(
								_('Remove ongoing token'),
								() =>
									this.selectCardAction(
										stateName,
										typedArgs.optionalSelection,
										this.getSelectedDuelStock(),
										{ option: 'removeOngoingToken' }
									),
								{}
							)
						} else {
							this.statusBar.addActionButton(
								_('Validate'),
								() =>
									this.selectCardAction(
										stateName,
										typedArgs.optionalSelection,
										this.getSelectedDuelStock(),
										{ qty: typedArgs.qty }
									),
								{}
							)
						}
					} else if (typedArgs.interactionType === 'selectCardFromHand') {
						this.statusBar.addActionButton(
							_('Validate selection'),
							() =>
								this.selectCardAction(
									stateName,
									typedArgs.optionalSelection,
									this.playerTables[this.getPlayerId()].handStock,
									{ qty: typedArgs.qty }
								),
							{}
						)
					} else {
						//this.setActionBarChooseAction(false)
						this.statusBar.addActionButton(
							_('Validate'),
							() => this.chooseDuelCardAction(this.playerTables[this.getPlayerId()].handStock),
							{}
						)
					}
					break
				case 'librarianAbility':
					this.statusBar.addActionButton(
						'+2',
						() => this.takeAction('actChooseModifier', { modifierValue: 2 }),
						{}
					)
					this.statusBar.addActionButton(
						'-2',
						() => this.takeAction('actChooseModifier', { modifierValue: -2 }),
						{}
					)
					break
				case 'blackmailerDiscard':
					this.statusBar.addActionButton(
						_('Validate'),
						() =>
							this.takeAction('actBlackmailerDiscard', {
								cardIds: this.playerTables[this.getPlayerId()].handStock
									.getSelection()
									.map((elt) => elt.id)
									.join(',')
							}),
						{}
					)

					break
			}
		}
	}

	private getSelectedDuelStock() {
		let i = 0
		let hasSelection = false
		let stockWithSelection = null
		const stocks = Object.values(this.centralZone.duelStocks)
		while (i < stocks.length && !hasSelection) {
			const selection = stocks[i].getSelection()
			hasSelection = selection.length !== 0
			if (hasSelection) stockWithSelection = stocks[i]
			i++
		}
		return stockWithSelection
	}

	private getSelectedDuelCard() {
		const stock = this.getSelectedDuelStock()
		const selection = stock.getSelection()
		const hasSelection = selection.length !== 0
		return hasSelection ? selection[0] : undefined
	}

	private chooseDuelCardAction(stock: CardStock<CardiaCard>) {
		this.ensureStockSelection([stock], _('You have to select a card'), () => {
			this.takeAction('actChooseDuelCard', {
				cardId: stock.getSelection()[0].id
			})
		})
	}

	private chooseScrapyardCardAction(stock: CardStock<CardiaCard>) {
		this.ensureStockSelection([stock], _('You have to select a card'), () => {
			this.takeAction('actScrapyardChooseCard', {
				cardId: stock.getSelection()[0].id
			})
		})
	}

	private serpentTempleDiscardAction(stock: CardStock<CardiaCard>) {
		this.ensureStockSelection([stock], _('You have to select a card'), () => {
			this.takeAction('actSerpentTempleDiscard', {
				cardId: stock.getSelection()[0].id
			})
		})
	}

	private selectCardAction(
		stateName: string,
		optionalSelection: boolean,
		stock?: CardStock<CardiaCard>,
		additionalParameters?: any
	) {
		const actionName = stateName == 'interactiveAbility' ? 'actInteractiveAbility' : 'actInteractiveAbilityStep2'
		const selectionSize = additionalParameters?.qty ?? 1
		log(additionalParameters)
		log(selectionSize)
		if (!optionalSelection) {
			if (!stock) {
				;(this as any).showMessage(
					this.format_string_recursive(_('You have to select ${qty} card(s)'), {
						'qty': selectionSize
					}),
					'error'
				)
				return
			}
			this.ensureStockSelection(
				[stock],
				this.format_string_recursive(_('You have to select ${qty} card(s)'), {
					'qty': selectionSize
				}),
				() => {
					this.takeAction(actionName, {
						cardIds: stock
							.getSelection()
							.map((elt) => elt.id)
							.join(','),
						...additionalParameters
					})
				},
				selectionSize
			)
		} else {
			this.takeAction(actionName, {
				cardIds:
					stock && stock.getSelection().length > 0
						? stock
								.getSelection()
								.map((elt) => elt.id)
								.join(',')
						: [],
				...additionalParameters
			})
		}
	}

	private selectFaction(faction: string) {
		this.takeAction('actInteractiveAbility', {
			faction: faction
		})
	}

	///////////////////////////////////////////////////
	//// Utility methods
	///////////////////////////////////////////////////

	/**
	 * Returns the player ID corresponding to the given position.
	 */
	public getPlayerIdFromPosition(position: number): number | null {
		const players = this.gamedatas.players
		for (const playerId in players) {
			if (players[playerId].playerNo === position) {
				return Number(playerId)
			}
		}
		return null
	}

	public isUserLocaleFrench() {
		const userLocale = navigator.language || navigator.languages[0]
		return userLocale.startsWith('fr-')
	}

	private getSelectedIdsAsParam(stock: CardStock<CardiaCard>) {
		return stock
			.getSelection()
			.map((c) => c.id)
			.join(',')
	}

	public isRealTime() {
		return (this as any).bRealtime
	}

	public closeCurrentTooltip() {
		if (this.displayedTooltip == null) return
		else {
			this.displayedTooltip.close()
			this.displayedTooltip = null
		}
	}

	public addTooltipOnClickHelpButton(id, html, delay) {
		let tooltip = new dijit.Tooltip({
			label: html,
			showDelay: delay
		})

		dojo.connect($(id), 'click', (evt) => {
			evt.stopPropagation()

			if (tooltip.state == 'SHOWING') {
				this.closeCurrentTooltip()
			} else {
				this.closeCurrentTooltip()
				tooltip.open($(id))
				this.displayedTooltip = tooltip
			}
		})

		dojo.connect($(id), 'mouseleave', () => {
			tooltip.close()
		})
	}

	public dontPreloadUselessAssets() {
		if (this.getPlayersCount() == 1) {
			//;(this as any).dontPreloadImage('centralBoard.png')//TODO
		} else {
			//;(this as any).dontPreloadImage('centralBoardSolo.png')
		}
	}

	public toggleActionButtonAbility(buttonId: string, enable: boolean, autoClickIfEnabled: boolean = undefined) {
		if (autoClickIfEnabled == undefined) {
			//autoClickIfEnabled= this.isConfirmOnlyOnPlacingTokensOn()
		}
		dojo.toggleClass(buttonId, 'disabled', !enable)
		if (autoClickIfEnabled && !dojo.hasClass(buttonId, 'disabled')) {
			$(buttonId).click()
		}
	}

	/** Tells if confirm is active in user prefs. */
	public isConfirmOnlyOnPlacingTokensOn(): boolean {
		//return (this as any).prefs[2].value == 1
		return true
	}

	public resetClientActionData() {
		this.clientActionData = {
			placedCardId: undefined,
			destinationSquare: undefined,
			previousCardParentInHand: undefined
		}
	}

	private setChooseActionGamestateDescription(newText?: string) {
		if (!this.originalTextChooseAction) {
			this.originalTextChooseAction = document.getElementById('pagemaintitletext').innerHTML
		}

		document.getElementById('pagemaintitletext').innerHTML = newText ?? this.originalTextChooseAction
	}

	/**
	 * Sets the action bar (title and buttons) for Choose action.
	 */
	private setActionBarChooseAction(fromCancel: boolean) {
		document.getElementById(`generalactions`).innerHTML = ''
		if (fromCancel) {
			this.setChooseActionGamestateDescription()
		}
		if (this.actionTimerId) {
			window.clearInterval(this.actionTimerId)
		}

		const chooseActionArgs = this.gamedatas.gamestate.args as EnteringChooseActionArgs

		this.addImageActionButton(
			'useTicket_button',
			createDiv('expTicket', 'expTicket-button'),
			'primary',
			_('Use a ticket to place another arrow, remove the last one of any expedition or exchange a card'),
			() => {
				// this.useTicket();
			}
		)
		$('expTicket-button').parentElement.style.padding = '0'

		//dojo.toggleClass('useTicket_button', 'disabled', !chooseActionArgs.canUseTicket);

		if (chooseActionArgs.canPass) {
			this.statusBar.addActionButton(_('End my turn'), () => this.pass(), {})
		}

		if (chooseActionArgs.canResetTurn) {
			this.statusBar.addActionButton(_('Reset my turn'), () => this.takeAction('actResetPlayerTurn'), {
				color: 'alert',
				title: _('Reset your entire round')
			})
		}
	}

	///////////////////////////////////////////////////
	//// Player's action

	/*
    
        Here, you are defining methods to handle player's action (ex: results of mouse click on 
        game objects).
        
        Most of the time, these methods:
        _ check the action is possible at this game state.
        _ make a call to the game server
    
    */
	private ensureStockSelection(
		stocks: CardStock<CardiaCard>[],
		errorMsg: string,
		callback: Function,
		selectionCount: number = 1
	) {
		if (stocks.every((s) => s.getSelection().length >= selectionCount)) {
			callback()
		} else {
			;(this as any).showMessage(errorMsg, 'error')
		}
	}

	///////////////////////////////////////////////////
	//// Reaction to cometD notifications

	/*
        setupNotifications:
        
        In this method, you associate each of your game notifications with your local method to handle it.
        
        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your cardia.game.php file.
    
    */
	setupNotifications() {
		log('notifications subscriptions setup')

		// TODO: here, associate your game notifications with local methods

		// Example 1: standard notification handling
		// dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );

		// Example 2: standard notification handling + tell the user interface to wait
		//            during 3 seconds after calling the method in order to let the players
		//            see what is happening in the game.
		// dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
		// this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
		//

		const notifs = [
			//['claimedRoute', ANIMATION_MS],
			['points', 1],
			//['score', ANIMATION_MS],
			['materialMove', ANIMATION_MS],
			['lastTurn', 1],
			['importantMessage', 3000],
			['counter', 1],
			['updateCounters', 1],
			['newRound', 1],
			['nextCardModifier', 1],
			['updateModifiers', 1],
			['duelResult', ANIMATION_MS * 2],
			['power', ANIMATION_MS * 2]
		]

		notifs.forEach((notif) => {
			dojo.subscribe(notif[0], this, `notif_${notif[0]}`)
			;(this as any).notifqueue.setSynchronous(notif[0], notif[1])
		})
	}

	notif_duelResult(notif: Notif<NotifDuelResult>) {
		log('notif_duelResult', notif)
		this.statusBar.setTitle(notif.log, notif.args)
		if (notif.args.winningCard) {
			return this.animationManager
				.play(
					new BgaCumulatedAnimation({
						animations: [
							new BgaPauseAnimation({
								animationClass: 'bounce-top',
								duration: 1000,
								classes: ['cardia-card'],
								element: this.cardsManager
									.getCardStock(notif.args.winningCard)
									.getCardElement(notif.args.winningCard)
							})
						]
					})
				)
				.then(() => {})
		}
	}

	notif_power(notif: Notif<NotifPower>) {
		log('notif_power', notif)
		this.statusBar.setTitle(notif.log, notif.args)
		if (notif.args.ability) {
			return this.animationManager
				.play(
					new BgaPauseAnimation({
						animationClass: 'shake-bottom',
						duration: 800,
						element: this.cardsManager.getCardStock(notif.args.ability).getCardElement(notif.args.ability)
					})
				)
				.then(() => {})
		}
		if (notif.args.location) {
			const location = document.querySelector('.location-help-button') ?? $('player_board_location')
			return this.animationManager
				.play(
					new BgaPauseAnimation({
						animationClass: 'heartbeat',
						duration: 1500,
						element: location
					})
				)
				.then(() => {})
		}
	}

	notif_newRound(notif: Notif<NotifScoreArgs>) {
		Object.keys(this.gamedatas.players).forEach((playerId) => {
			this.discards[playerId].removeAll()
			this.playerTables[playerId].handStock?.removeAll()
		})
		this.centralZone.resetDuelStocks()
	}

	notif_counter(notif: Notif<NotifCounter>) {
		if (notif.args.counterName == 'empty-hexes') {
			//this.emptyHexesCounters[notif.args.playerId].setValue(notif.args.counterValue)
		}
	}

	notif_updateModifiers(notif: Notif<NotifUpdateModifiers>) {
		this.updateModifiers(notif.args.modifiers)
	}

	notif_nextCardModifier(notif: Notif<NotifNextCardModifier>) {
		this.updateNextCardModifier(notif.args.playerId, notif.args.playerPosition, notif.args.value)
	}

	updateNextCardModifier(playerId: number, playerPosition: number, value: number) {
		const duelId = this.centralZone.createDuelStock(null, null)
		const slotQuery = `#${duelId} .slot[data-slot-id="${playerPosition}"]`
		const slot = document.querySelector(slotQuery) as HTMLElement
		this.updateModifierOnLastSlot(slot, value, playerId)
	}

	notif_materialMove(notif: Notif<NotifMaterialMove>) {
		log('notif_materialMove', notif)
		switch (notif.args.type) {
			case 'CARD':
				const cards = notif.args.material as Array<CardiaCard>
				this.notif_cardMove(cards, notif)
				break
			case 'TOKEN':
				const tokens = notif.args.material as Array<Token>
				this.notif_tokenMove(tokens, notif)
				break
			case 'ONGOING_TOKEN':
				const ongoingTokens = notif.args.material as Array<Token>
				this.notif_onGoingTokenMove(ongoingTokens, notif)
				break
			default:
				console.error('Material type move not handled', notif)
				break
		}
	}

	private notif_tokenMove(tokens: Token[], notif: Notif<NotifMaterialMove>) {
		const card = tokens.at(0)
		switch (notif.args.to) {
			case 'card':
				this.createSignetOnCard(card)
				break
			case 'deck':
				this.removeSignetOnCard(card)
				break
			default:
				console.error('Token move destination not handled', notif)
				break
		}
	}

	private notif_onGoingTokenMove(tokens: Token[], notif: Notif<NotifMaterialMove>) {
		const card = tokens.at(0)
		switch (notif.args.to) {
			case 'card':
				this.createOnGoingTokenOnCard(card)
				break
			case 'deck':
				this.removeOnGoingTokenOnCard(card)
				break
			default:
				console.error('Token move destination not handled', notif)
				break
		}
	}

	private notif_cardMove(cards: CardiaCard[], notif: Notif<NotifMaterialMove>) {
		const card = cards.at(0)

		switch (notif.args.to) {
			case 'discard':
				this.discards[notif.args.toArg].addCard(card, {
					fromElement: $(`overall_player_board_${notif.args.toArg}`)
				})
				this.updateModifierOnElement($(`cardia-card-${card.id}-modifier-value`), 0)
				break
			case 'hand':
				//log('toArg', notif.args.toArg, this.getPlayerId())
				if (notif.args.toArg == this.getPlayerId()) {
					this.playerTables[notif.args.toArg]?.handStock.addCards(cards, {
						fromElement:
							notif.args.from == 'deck' ? $(`deck-cards-counter-${notif.args.toArg}-wrapper`) : undefined
					})
				} else {
					log('removeCard', card.name)
					cards.forEach((c) => {
						this.cardsManager.getCardStock(card)?.removeCard(c)
					})
				}
				break
			case 'encounter':
				//log('revealed', card.name, card.type_arg, card.id)
				if (notif.args.toArg) {
					//only one encounter is considered
					this.addCardToEncounter(card, notif.args.toArg)
				} else {
					//it’s a reorganization of duels
					cards.forEach((card) => {
						const stock = this.centralZone.duelStocks[card.location_arg]
						stock.addCard(card)
					})
				}
				break
			case 'deck':
				cards.forEach((c) => {
					this.cardsManager.getCardStock(card)?.removeCard(c)
				})
				break
			default:
				console.error('Card move destination not handled', notif)
				break
		}
	}

	private addCardToEncounter(card: CardiaCard, encounterNumber: number) {
		dojo.query('.temp-modifier').forEach((el) => dojo.destroy(el))

		let stock = this.centralZone.duelStocks[encounterNumber]
		if (!stock) {
			this.centralZone.createDuelStock(null, null) //one for the current duel
			//this.centralZone.createDuelStock(null, null) //one to prepare the next
			stock = this.centralZone.duelStocks[encounterNumber]
		}

		//log('addCardToEncounter', card.name, encounterNumber, card.id)
		stock
			.addCard(
				card,
				{ fromElement: $(`hand-cards-counter-${this.getPlayerIdFromPosition(card.type_arg)}-wrapper`) },
				{ updateInformations: false }
			)
			.then(
				(success) => {
					//log('updateCardInformations success', card.name, card.id, "animation", success)
					//debugger
					this.cardsManager.updateCardInformations(card) //flip the card if needed,
				},
				(error) => {
					//log('updateCardInformations error', card.name, card.id, "animation", error)
					//debugger
					this.cardsManager.updateCardInformations(card) //flip the card if needed
				}
			)
	}
}

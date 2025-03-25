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

const IMAGE_ITEMS_PER_ROW = 10
const ACTION_TIMER_DURATION = 6

class Cardia extends BaseGame implements CardiaGame {
	public cardsManager: CardsManager
	private originalTextChooseAction: string

	private scoreBoard: ScoreBoard
	private ticketsCounters: Counter[] = []
	private handCardsCounters: Counter[] = []

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

		this.cardsManager = new CardsManager(this)
		this.animationManager = new AnimationManager(this)

		if (gamedatas.lastTurn) {
			this.notif_lastTurn(false)
		}
		if (Number(gamedatas.gamestate.id) >= 90) {
			// score or end
			this.onEnteringEndScore()
		}

		Object.values(this.gamedatas.playerOrderWorkingWithSpectators).forEach((p) => {
			this.setupPlayer(this.gamedatas.players[p])
		})
		;(this as any).updateCounters(this.gamedatas.counters)

		$('overall-content').classList.add(`player-count-${this.getPlayersCount()}`)

		this.setupPreferences()
		this.setupTooltips()
		this.setupHelpPopin()

		this.scoreBoard = new ScoreBoard(this, this.getPlayersInOrder())
		this.gamedatas.scores?.forEach((s) => this.scoreBoard.updateScore(s.playerId, s.scoreType, s.score))
		if (this.gamedatas.winners) {
			this.gamedatas.winners.forEach((pId) => this.scoreBoard.highlightWinnerScore(pId))
		}
		removeClass('animatedScore')
		this.setupNotifications()

		log('Ending game setup')
	}

	private setupTooltips() {
		//todo change counter names
		this.setTooltipToClass('revealed-tokens-back-counter', _('counter1 tooltip'))
		this.setTooltipToClass('tickets-counter', _('counter2 tooltip'))
		this.setTooltipToClass('hand-cards-counter', _('Cards in hand'))

		this.setTooltipToClass('cstm-help-icon', `<div class="help-card recto"></div>`)
		this.setTooltipToClass('cstm-help-icon-mini', `<div class="help-card verso"></div>`)
		this.setTooltipToClass('player-turn-order', _('First player'))
	}

	private setupPlayer(player: CardiaPlayer) {
		document.getElementById(`overall_player_board_${player.id}`).dataset.playerColor = player.color
		if (this.gameFeatures.showPlayerOrderHints) {
			this.setupPlayerOrderHints(player)
		}
		this.setupMiniPlayerBoard(player)
		this.playerTables[player.id] = new PlayerTable(this, player)
	}

	private setupMiniPlayerBoard(player: CardiaPlayer) {
		const playerId = Number(player.id)
		dojo.place(
			`<div id="counters-${player.id}" class="counters">
				<div id="tickets-counter-${player.id}-wrapper" class="counter tickets-counter">
					<div class="icon expTicket"></div> 
					<span id="tickets-counter-${player.id}"></span>
				</div>
			
				<div id="hand-cards-counter-${player.id}-wrapper" class="counter hand-cards-counter counter-left-part">
					<div class="fa fa-hand-paper-o"></div> 
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
		new HelpManager(this, {
			buttons: [
				new BgaHelpPopinButton({
					title: _('Roles in play'),
					html: this.getHelpHtml(),
					buttonBackground: 'white',
					buttonColor: '#266059'
				})
			]
		})
	}

	private getHelpHtml() {
		let html = `
        <div id="help-popin"> `
		/*new Set(this.gamedatas.rolesInPlay).forEach((r) => {
			html += this.getRoleHtml(r, this.gamedatas.rolesInPlay.filter((allR) => allR === r).length)
		})*/
		html += `
        </div>
        `
		return html
	}

	///////////////////////////////////////////////////
	//// Game & client states

	// onEnteringState: this method is called each time we are entering into a new game state.
	//                  You can use this method to perform some user interface changes at this moment.
	//
	public onEnteringState(stateName: string, args: any) {
		log('Entering state: ' + stateName, args)

		switch (stateName) {
			case 'chooseAction':
				if (args?.args) {
					const dataArgs = args.args as EnteringChooseActionArgs
					this.onEnteringChooseAction(dataArgs)
				}
				break
			case 'endScore':
				this.onEnteringEndScore()
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

	private getPossibleActions(args: EnteringChooseActionArgs) {
		const actions = []

		//if (args.canBuild) actions.push(_('Build your mall'))
		//if (args.canTakeMoney) actions.push(_('Take money from the dispenser'))

		if (actions.length === 0) {
			actions.push(_('No possible action left'))
		}
		return actions
	}

	/**
	 * Show score board.
	 */
	private onEnteringEndScore() {
		const lastTurnBar = document.getElementById('last-round')
		if (lastTurnBar) {
			lastTurnBar.style.display = 'none'
		}

		document.getElementById('score').style.display = 'flex'
	}

	// onLeavingState: this method is called each time we are leaving a game state.
	//                 You can use this method to perform some user interface changes at this moment.
	//
	public onLeavingState(stateName: string) {
		log('Leaving state: ' + stateName)

		switch (stateName) {
			/* Example:
        
        case 'myGameState':
        
            // Hide the HTML block we are displaying only during this game state
            dojo.style( 'my_html_block_id', 'display', 'none' );
            
            break;
        */

			case 'dummmy':
				break
		}
	}

	// onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
	//                        action status bar (ie: the HTML links in the status bar).
	//
	public onUpdateActionButtons(stateName: string, args: any) {
		log('onUpdateActionButtons: ' + stateName)

		if ((this as any).isCurrentPlayerActive()) {
			switch (stateName) {
				case 'chooseDuelCard':
					this.statusBar.addActionButton(_('Validate'), () => this.chooseDuelCardAction(), {})
					//this.setActionBarChooseAction(false)
					break
			}
		}
	}

	private chooseDuelCardAction() {
		this.ensureStockSelection(
			[this.playerTables[this.getPlayerId()].handStock],
			_('You have to select a card'),
			() => {
				this.takeAction('actChooseDuelCard', {
					cardId: this.playerTables[this.getPlayerId()].handStock.getSelection()[0].id
				})
			}
		)
	}

	///////////////////////////////////////////////////
	//// Utility methods
	///////////////////////////////////////////////////
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
	private ensureStockSelection(stocks: CardStock<CardiaCard>[], errorMsg: string, callback: Function) {
		if (stocks.every((s) => s.getSelection().length > 0)) {
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
			['score', ANIMATION_MS],
			['highlightWinnerScore', ANIMATION_MS],
			['materialMove', ANIMATION_MS],
			['lastTurn', 1],
			['importantMessage', 3000],
			['counter', 1],
			['updateCounters', 1]
		]

		notifs.forEach((notif) => {
			dojo.subscribe(notif[0], this, `notif_${notif[0]}`)
			;(this as any).notifqueue.setSynchronous(notif[0], notif[1])
		})
	}

	/**
	 * Updates a total or subtotal
	 * @param notif
	 */
	notif_score(notif: Notif<NotifScoreArgs>) {
		log('notif_score', notif)
		this.scoreBoard.updateScore(notif.args.playerId, notif.args.scoreType, notif.args.score)
	}

	notif_counter(notif: Notif<NotifCounter>) {
		if (notif.args.counterName == 'empty-hexes') {
			//this.emptyHexesCounters[notif.args.playerId].setValue(notif.args.counterValue)
		}
	}

	notif_materialMove(notif: Notif<NotifMaterialMove>) {
		log('notif_materialMove', notif)
		/*switch (notif.args.type) {
			case "MISSION":
				const cards = notif.args.material as Array<MissionCard>
				this.notif_missionMove(cards, notif)
				break
			default:
				console.error('Material type move not handled', notif)
				break
		}*/
	}

	/*private notif_missionMove(cards: MissionCard[], notif: Notif<NotifMaterialMove>) {
		const card = cards.at(0)
		switch (notif.args.to) {
			case "DISCARD":
				if (notif.args.fromArg == notif.args.toArg) {
					this.festivalStocks[notif.args.toArg].flipCard(card)
					if (notif.args?.soldOut) this.playCustomSound('clap', false)
				} else {
					this.festivalStocks[notif.args.toArg].addCard(card)
				}
				break

			default:
				console.error('Festival move destination not handled', notif)
				break
		}
	}*/

	/**
	 * Highlight winner for end score.
	 */
	notif_highlightWinnerScore(notif: Notif<NotifWinnerArgs>) {
		this.scoreBoard?.highlightWinnerScore(notif.args.playerId)
	}
}

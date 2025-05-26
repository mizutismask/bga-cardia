const ANIMATION_MS = 500
const SCORE_MS = 1500

const isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1
const log = isDebug ? console.log.bind(window.console) : function () {}

abstract class BaseGame {
	protected player_id: string
	protected players: { [playerId: number]: Player }
	protected playerTables: { [playerId: number]: PlayerTable } = []
	protected playerNumber: number
	public animationManager: AnimationManager
	protected actionTimerId = null
	protected isTouch = window.matchMedia('(hover: none)').matches
	protected TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined
	protected gameFeatures: GameFeatureConfig

	public clientActionData: ClientActionData
	protected gamedatas: CardiaGamedatas
	protected statusBar: StatusBar
	protected animations: CardiaAnimation[] = []
	protected customSounds = [
		//'sound1'
	]
	protected contentAnchor = `game_play_area`

	protected abstract settings: Setting[]
	/*
	Settings container from the hamburger menu, used to put things at their original place when resetting turn,
	to avoid repeating errors because player panels are emptied then settings disappear.
	*/
	private bgaSettingsParent

	constructor() {
		log('Base game constructor')
	}

	protected includeHtmlBasicTemplate() {
		this.customSounds.forEach((sound) => {
			dojo.place(
				`
				<audio id="audiosrc_${sound}" src="${g_gamethemeurl}img/${sound}.mp3" preload="none"></audio>
				<audio id="audiosrc_o_${sound}" src="${g_gamethemeurl}img/${sound}.ogg" preload="none"></audio>
				`,
				this.contentAnchor,
				'first'
			)
		})

		dojo.place(
			`
		<div id="custom-game-area">
			<div id="location-hand">
				<div id="location-area"></div>
				<div id="player-tables"></div>
			</div>
			<div id="void-area"></div>
		</div>`,
			'game_play_area_background',
			'after'
		)
	}

	/**
	 * Get current player.
	 */
	public getCurrentPlayer(): CardiaPlayer {
		return this.gamedatas.players[this.getPlayerId()]
	}

	public getPlayerId(): number {
		return Number((this as any).player_id)
	}

	public getPlayerScore(playerId: number): number {
		return (this as any).scoreCtrl[playerId]?.getValue() ?? Number(this.gamedatas.players[playerId].score)
	}

	public getPlayersCount(): number {
		return Object.values(this.gamedatas.players).length
	}

	public getOpponentId(playerId: string) {
		const players = Object.keys(this.gamedatas.players)
		if (players.length != 2) throw new Error('Impossible to know who is the opponent in a non 2 players game')
		return players.filter((player) => player !== playerId)[0]
	}

	public isNotSpectator() {
		//log('isSpectator', (this as any).isSpectator)
		return (
			(this as any).isSpectator == false ||
			Object.keys(this.gamedatas.players).includes(this.getPlayerId().toString())
		)
	}

	public setGamestateDescription(property: string = '') {
		const originalState = this.gamedatas.gamestates[this.gamedatas.gamestate.id]
		this.gamedatas.gamestate.description = originalState['description' + property]
		this.gamedatas.gamestate.descriptionmyturn = originalState['descriptionmyturn' + property]
		;(this as any).updatePageTitle()
	}

	public setupPlayerOrderHints(player: CardiaPlayer) {
		const nameDiv: HTMLElement = document.querySelector('#player_name_' + player.id + ' a')
		const surroundingPlayers = this.getSurroundingPlayersIds(player)
		const previousId = this.gamedatas.turnOrderClockwise ? surroundingPlayers[0] : surroundingPlayers[1]
		const nextId = this.gamedatas.turnOrderClockwise ? surroundingPlayers[1] : surroundingPlayers[0]

		this.updatePlayerHint(player, previousId, '_previous_player', _('Previous player: '), '&lt;', nameDiv, 'before')
		this.updatePlayerHint(player, nextId, '_next_player', _('Next player: '), '&gt;', nameDiv, 'after')
	}

	public updatePlayerHint(
		currentPlayer: CardiaPlayer,
		otherPlayerId: string | number,
		divSuffix: string,
		titlePrefix: string,
		content: string,
		parentDivId: HTMLElement,
		location: string
	) {
		if (!$(currentPlayer.id + divSuffix)) {
			dojo.create(
				'span',
				{
					id: currentPlayer.id + divSuffix,
					class: 'playerOrderHelp',
					title: titlePrefix + this.gamedatas.players[otherPlayerId].name,
					style: 'color:#' + this.gamedatas.players[otherPlayerId]['color'] + ';',
					innerHTML: content
				},
				parentDivId,
				location
			)
		}
	}

	/**
	 * Gets the player ids of the previous and the next player regarding the player given in parameter
	 * @param player
	 * @returns an array with the previous player at 0 and the next player at 1
	 */
	public getSurroundingPlayersIds(player: CardiaPlayer) {
		let playerIndex = this.gamedatas.playerorder.indexOf(parseInt(player.id)) //playerorder is a mixed types array
		if (playerIndex == -1) playerIndex = this.gamedatas.playerorder.indexOf(player.id)

		const previousId =
			playerIndex - 1 < 0
				? this.gamedatas.playerorder[this.gamedatas.playerorder.length - 1]
				: this.gamedatas.playerorder[playerIndex - 1]
		const nextId =
			playerIndex + 1 >= this.gamedatas.playerorder.length
				? this.gamedatas.playerorder[0]
				: this.gamedatas.playerorder[playerIndex + 1]

		return [previousId, nextId]
	}

	public addArrowsToActivePlayer(state: Gamestate) {
		const notUsefulStates = ['todo']
		if (
			state.type === 'activeplayer' &&
			state.active_player !== this.player_id &&
			!notUsefulStates.includes(state.name)
		) {
			if (!$('goToCurrentPlayer')) {
				dojo.place(
					`
                    <div id="goToCurrentPlayer" class="show-player-tableau">
                        <a href="#anchor-player-${state.active_player}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 85.333343 145.79321">
                                <path fill="currentColor" d="M 1.6,144.19321 C 0.72,143.31321 0,141.90343 0,141.06039 0,140.21734 5.019,125.35234 11.15333,108.02704 L 22.30665,76.526514 14.626511,68.826524 C 8.70498,62.889705 6.45637,59.468243 4.80652,53.884537 0.057,37.810464 3.28288,23.775161 14.266011,12.727735 23.2699,3.6711383 31.24961,0.09115725 42.633001,0.00129225 c 15.633879,-0.123414 29.7242,8.60107205 36.66277,22.70098475 8.00349,16.263927 4.02641,36.419057 -9.54327,48.363567 l -6.09937,5.36888 10.8401,30.526466 c 5.96206,16.78955 10.84011,32.03102 10.84011,33.86992 0,1.8389 -0.94908,3.70766 -2.10905,4.15278 -1.15998,0.44513 -19.63998,0.80932 -41.06667,0.80932 -28.52259,0 -39.386191,-0.42858 -40.557621,-1.6 z M 58.000011,54.483815 c 3.66666,-1.775301 9.06666,-5.706124 11.99999,-8.735161 l 5.33334,-5.507342 -6.66667,-6.09345 C 59.791321,26.035633 53.218971,23.191944 43.2618,23.15582 33.50202,23.12041 24.44122,27.164681 16.83985,34.94919 c -4.926849,5.045548 -5.023849,5.323672 -2.956989,8.478106 3.741259,5.709878 15.032709,12.667218 24.11715,14.860013 4.67992,1.129637 13.130429,-0.477436 20,-3.803494 z m -22.33337,-2.130758 c -2.8907,-1.683676 -6.3333,-8.148479 -6.3333,-11.893186 0,-11.58942 14.57544,-17.629692 22.76923,-9.435897 8.41012,8.410121 2.7035,22.821681 -9,22.728685 -2.80641,-0.0223 -6.15258,-0.652121 -7.43593,-1.399602 z m 14.6667,-6.075289 c 3.72801,-4.100734 3.78941,-7.121364 0.23656,-11.638085 -2.025061,-2.574448 -3.9845,-3.513145 -7.33333,-3.513145 -10.93129,0 -13.70837,13.126529 -3.90323,18.44946 3.50764,1.904196 7.30574,0.765377 11,-3.29823 z m -11.36999,0.106494 c -3.74071,-2.620092 -4.07008,-7.297494 -0.44716,-6.350078 3.2022,0.837394 4.87543,-1.760912 2.76868,-4.29939 -1.34051,-1.615208 -1.02878,-1.94159 1.85447,-1.94159 4.67573,0 8.31873,5.36324 6.2582,9.213366 -1.21644,2.27295 -5.30653,5.453301 -7.0132,5.453301 -0.25171,0 -1.79115,-0.934022 -3.42099,-2.075605 z"></path>
                            </svg>
                        </a>
                    </div>
                    `,
					'generalactions',
					'last'
				)
			}
			if (!$('goBackUp')) {
				dojo.place(
					`
                    <div id="goBackUp" class="show-player-tableau">
                        <a href="#">
                            <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="1280.000000pt" height="1280.000000pt" viewBox="0 0 1280.000000 1280.000000" preserveAspectRatio="xMidYMid meet">
                                <g transform="translate(0.000000,1280.000000) scale(0.100000,-0.100000)"
                                fill="currentColor" stroke="none">
                                <path d="M6305 12787 c-74 -19 -152 -65 -197 -117 -30 -34 -786 -1537 -3070
                                -6105 -2924 -5849 -3029 -6062 -3035 -6126 -15 -173 76 -326 237 -403 59 -27
                                74 -30 160 -30 79 1 104 5 150 26 30 13 1359 894 2953 1956 l2897 1932 2897
                                -1932 c1594 -1062 2923 -1943 2953 -1957 47 -21 70 -25 150 -25 86 0 101 3
                                160 30 36 17 86 50 111 72 88 79 140 223 124 347 -6 51 -383 811 -3040 6125
                                -2901 5801 -3036 6069 -3082 6110 -100 90 -246 128 -368 97z"/>
                                </g>
                            </svg>
                        </a>
                    </div>
                    `,
					'generalactions',
					'last'
				)
			}
		}
	}

	/** Tells if seasons custom sounds are active in user prefs. */
	public isCustomSoundsOn(): boolean {
		return (this as any).prefs[1].value == 1
	}

	/*
	 * Play a given sound that should be first added in the tpl file
	 */
	public playCustomSound(sound: string, playNextMoveSound = true) {
		if (this.isCustomSoundsOn()) {
			;(this as any).playSound(sound)
			playNextMoveSound && (this as any).disableNextMoveSound()
		}
	}

	/**
	 * This method can be used instead of addActionButton, to add a button which is an image (i.e. resource). Can be useful when player
	 * need to make a choice of resources or tokens.
	 */
	public addImageActionButton(
		id: string,
		content: string,
		color: 'primary' | 'secondary' | 'alert' = 'primary',
		tooltip: string,
		handler,
		parentClass: string = ''
	) {
		// this will actually make a transparent button
		const btn = this.statusBar.addActionButton(content, handler, {
			id: id,
			color: color,
			title: tooltip,
			classes: 'shadow bgaimagebutton ' + parentClass
		})
		// remove boarder, for images it better without
		dojo.style(btn, 'border', 'none')
		return btn
	}

	/**
	 * Update player score.
	 */
	notif_points(notif: Notif<NotifPointsArgs>) {
		this.setPoints(notif.args.playerId, notif.args.points)
	}

	notif_updateCounters(notif: Notif<NotifUpdateCounters>) {
		this.safeUpdateCounters(notif.args.counters)
	}

	public safeUpdateCounters(counters) {
		const existingCounters = Object.keys(counters).filter((c) => $(c) != undefined)
		;(this as any).updateCounters(Object.fromEntries(existingCounters.map((key) => [key, counters[key]])))

		const notExistingCounters = Object.keys(counters).filter((c) => $(c) == undefined)
		this.updateCustomCounters(Object.fromEntries(notExistingCounters.map((key) => [key, counters[key]])))
	}

	public updateCustomCounters(counters) {
		
	}

	/**
	 * Update player score.
	 */
	public setPoints(playerId: number, points: number) {
		;(this as any).scoreCtrl[playerId]?.toValue(points)
	}

	/**
	 * Show last turn banner.
	 */
	notif_lastTurn(animate: boolean = true) {
		if (!$('last-round')) {
			dojo.place(
				`<div id="last-round">
					<span class="last-round-text ${animate ? 'animate' : ''}">${_('Finishing round before end of game!')}</span>
				</div>`,
				'page-title'
			)
		}
	}

	/**
	 * Show important message banner.
	 */
	notif_importantMessage(notif: Notif<NotifImportantMessageArgs>, animate: boolean = true) {
		let msgClass = ''
		switch (notif.args.type) {
			case 'POSITIVE':
				msgClass = 'important-msg-positive'
				break
			case 'NEGATIVE':
				msgClass = 'important-msg-negative'
				break
			case 'WARNING':
				msgClass = 'important-msg-warning'
				break
		}
		dojo.place(
			`<div id="important-message" class="${msgClass}">
				<span class="important-message-text ${animate ? 'animate' : ''}">${this.format_string_recursive(
				notif.args.message,
				notif.args
			)}</span>
			</div>`,
			'page-title'
		)
		if (notif.args.temporary) {
			;(this as any).fadeOutAndDestroy('important-message', 4000)
		}
	}

	public destroyImportantMessage() {
		if ($('important-message')) (this as any).fadeOutAndDestroy('important-message', 300)
	}

	/* This enable to inject translatable styled things to logs or action bar */
	/* @Override */
	public format_string_recursive(log: string, args: any) {
		try {
			if (log && args && !args.processed) {
				;['you', 'actplayer', 'player_name'].forEach((field) => {
					if (
						typeof args[field] === 'string' &&
						args[field].indexOf('#df74b2;') !== -1 &&
						args[field].indexOf('text-shadow') === -1
					) {
						args[field] = args[field].replace(
							'#df74b2;',
							'#df74b2; text-shadow: 0 0 1px black, 0 0 2px black, 0 0 3px black;'
						)
					}
				})
			}
		} catch (e) {
			console.error(log, args, 'Exception thrown', e.stack)
		}
		return (this as any).inherited(arguments)
	}
	/**
	 * Get current zoom.
	 */
	public getZoom(): number {
		return 1
	}

	public takeAction(action: string, data?: any, options?: { lock: boolean; checkAction: boolean }): Promise<void> {
		data = data || {}
		data.version = this.gamedatas.version
		log("takeAction", action, data, options)
		return (this as any).bgaPerformAction(action, data, options)
	}

	public setTooltip(id: string, html: string) {
		;(this as any).addTooltipHtml(id, html, this.TOOLTIP_DELAY)
	}
	public setTooltipToClass(className: string, html: string) {
		;(this as any).addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY)
	}
	/**
	 * Add an animation to the animation queue, and start it if there is no current animations.
	 */
	public addAnimation(animation: CardiaAnimation) {
		this.animations.push(animation)
		if (this.animations.length === 1) {
			this.animations[0].animate()
		}
	}

	/**
	 * Start the next animation in animation queue.
	 */
	public endAnimation(ended: CardiaAnimation) {
		const index = this.animations.indexOf(ended)
		if (index !== -1) {
			this.animations.splice(index, 1)
		}
		if (this.animations.length >= 1) {
			this.animations[0].animate()
		}
	}

	/**
	 * Pass (in case of no possible action).
	 */
	public pass() {
		this.takeAction('actPass')
	}

	/**From Agricola */
	public slide(mobileElt, targetElt, options = {}) {
		let config = Object.assign(
			{
				duration: 800,
				delay: 0,
				destroy: false,
				attach: true,
				changeParent: true, // Change parent during sliding to avoid zIndex issue
				pos: null,
				className: 'moving',
				from: null,
				clearPos: true,
				beforeBrother: null,

				phantom: false,
				phantomStart: false,
				phantomEnd: false
			},
			options
		)
		config.phantomStart = config.phantomStart || config.phantom
		config.phantomEnd = config.phantomEnd || config.phantom

		// Mobile elt
		mobileElt = $(mobileElt)
		let mobile = mobileElt
		// Target elt
		targetElt = $(targetElt)
		let targetId = targetElt
		const newParent = config.attach ? targetId : $(mobile).parentNode

		// Handle fast mode
		if (this.isFastMode() && (config.destroy || config.clearPos)) {
			if (config.destroy) dojo.destroy(mobile)
			else dojo.place(mobile, targetElt)

			return new Promise((resolve, reject) => {
				//resolve();
				resolve.apply(this)
			})
		}

		// Handle phantom at start
		if (config.phantomStart) {
			mobile = dojo.clone(mobileElt)
			dojo.attr(mobile, 'id', mobileElt.id + '_animated')
			dojo.place(mobile, 'game_play_area')
			;(this as any).placeOnObject(mobile, mobileElt)
			dojo.addClass(mobileElt, 'phantom')
			config.from = mobileElt
		}

		// Handle phantom at end
		if (config.phantomEnd) {
			targetId = dojo.clone(mobileElt)
			dojo.attr(targetId, 'id', mobileElt.id + '_afterSlide')
			dojo.addClass(targetId, 'phantomm')
			if (config.beforeBrother != null) {
				dojo.place(targetId, config.beforeBrother, 'before')
			} else {
				dojo.place(targetId, targetElt)
			}
		}

		dojo.style(mobile, 'zIndex', 5000)
		dojo.addClass(mobile, config.className)
		if (config.changeParent) this.changeParent(mobile, 'game_play_area')
		if (config.from != null) (this as any).placeOnObject(mobile, config.from)
		return new Promise((resolve, reject) => {
			const animation =
				config.pos == null
					? (this as any).slideToObject(mobile, targetId, config.duration, config.delay)
					: (this as any).slideToObjectPos(
							mobile,
							targetId,
							config.pos.x,
							config.pos.y,
							config.duration,
							config.delay
					  )

			dojo.connect(animation, 'onEnd', () => {
				dojo.style(mobile, 'zIndex', null)
				dojo.removeClass(mobile, config.className)
				if (config.phantomStart) {
					dojo.place(mobileElt, mobile, 'replace')
					dojo.removeClass(mobileElt, 'phantom')
					mobile = mobileElt
				}
				if (config.changeParent) {
					if (config.phantomEnd) dojo.place(mobile, targetId, 'replace')
					else this.changeParent(mobile, newParent)
				}
				if (config.destroy) dojo.destroy(mobile)
				if (config.clearPos && !config.destroy) dojo.style(mobile, { top: null, left: null, position: null })
				//resolve();
				resolve.apply(this)
			})
			animation.play()
		})
	}

	public changeParent(mobile, new_parent, relation?) {
		if (mobile === null) {
			console.error('attachToNewParent: mobile obj is null')
			return
		}
		if (new_parent === null) {
			console.error('attachToNewParent: new_parent is null')
			return
		}
		if (typeof mobile == 'string') {
			mobile = $(mobile)
		}
		if (typeof new_parent == 'string') {
			new_parent = $(new_parent)
		}
		if (typeof relation == 'undefined') {
			relation = 'last'
		}
		var src = dojo.position(mobile)
		dojo.style(mobile, 'position', 'absolute')
		dojo.place(mobile, new_parent, relation)
		var tgt = dojo.position(mobile)
		var box = dojo.marginBox(mobile)
		var cbox = dojo.contentBox(mobile)
		var left = box.l + src.x - tgt.x
		var top = box.t + src.y - tgt.y
		this.positionObjectDirectly(mobile, left, top)
		box.l += box.w - cbox.w
		box.t += box.h - cbox.h
		return box
	}

	public isFastMode() {
		return (this as any).instantaneousMode
	}

	public positionObjectDirectly(mobileObj, x, y) {
		// do not remove this "dead" code some-how it makes difference
		dojo.style(mobileObj, 'left') // bug? re-compute style
		// console.log("place " + x + "," + y);
		dojo.style(mobileObj, {
			left: x + 'px',
			top: y + 'px'
		})
		dojo.style(mobileObj, 'left') // bug? re-compute style
	}

	public getPlayersInOrder() {
		return Object.values(this.gamedatas.playerOrderWorkingWithSpectators).map(
			(p) => this.gamedatas.players[Number(p)]
		)
	}

	/**
	 * Adds a button with each player name except for the current player
	 * @param buttonHandler what to do when one of the buttons is clicked
	 */
	public addPlayerNameButtons(buttonHandler: (p: CardiaPlayer) => void) {
		Object.values(this.gamedatas.players).forEach((p) => {
			if (Number(p.id) != this.getPlayerId()) {
				this.statusBar.addActionButton(
					this.gamedatas.players[p.id]?.name,
					function () {
						buttonHandler(p)
					},
					{ id: `choose_player_button_${p.id}` }
				)
			}
		})
	}

	public addTimerButton(
		buttonId: string,
		buttonText = _('Confirm'),
		activateCondition: boolean,
		confirmFunction,
		cancelButtonText: string,
		cancelFunction
	) {
		//this.stopActionTimer();
		if (activateCondition) {
			this.statusBar.addActionButton(
				buttonText,
				() => {
					dojo.destroy(buttonId)
					confirmFunction()
				},
				{ id: buttonId, classes: 'timer-button' }
			)

			this.startActionTimer(buttonId, isDebug ? 2 : ACTION_TIMER_DURATION, cancelButtonText, () => {
				dojo.destroy(buttonId)
				cancelFunction()
			})
		} else {
			this.stopActionTimer()
		}
	}

	/**
	 * Handle user preferences changes.
	 */
	protected setupPreferences() {
		// Extract the ID and value from the UI control
		const onchange = (e) => {
			const match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/)
			if (!match) {
				return
			}
			let prefId = +match[1]
			let prefValue = +e.target.value
			;(this as any).prefs[prefId].value = prefValue
			this.onPreferenceChange(prefId, prefValue)
		}

		// Call onPreferenceChange() when any value changes
		dojo.query('.preference_control').connect('onchange', onchange)

		// Call onPreferenceChange() now
		dojo.forEach(dojo.query('#ingame_menu_content .preference_control'), (el) => onchange({ target: el }))
	}

	/**
	 * Handle user preferences changes.
	 */
	public onPreferenceChange(prefId: number, prefValue: number) {
		log('onPreferenceChange', prefId, '=>', prefValue)
		switch (prefId) {
		}
	}

	/**
	 * Timer for Confirm button. Also adds a cancel button to stop timer.
	 * Cancel actions can be passed to be executed on cancel button click.
	 */
	public startActionTimer(buttonId: string, time: number, cancelButtonLabel = _('Cancel'), cancelFunction?) {
		if (this.actionTimerId) {
			window.clearInterval(this.actionTimerId)
			dojo.query('.timer-button').forEach((but: HTMLElement) => (but.innerHTML = this.stripTime(but.innerHTML)))
			dojo.destroy(`cancel-button`)
		}

		//adds cancel button
		const button = document.getElementById(buttonId)
		this.statusBar.addActionButton(
			cancelButtonLabel,
			() => {
				window.clearInterval(this.actionTimerId)
				button.innerHTML = this.stripTime(button.innerHTML)
				cancelFunction?.()
				dojo.destroy(`cancel-button`)
			},
			{ id: `cancel-button`, color: 'alert' }
		)

		const _actionTimerLabel = button.innerHTML
		let _actionTimerSeconds = time

		const actionTimerFunction = () => {
			const button = document.getElementById(buttonId)
			if (button == null) {
				window.clearInterval(this.actionTimerId)
			} else if (button.classList.contains('disabled')) {
				window.clearInterval(this.actionTimerId)
				button.innerHTML = this.stripTime(button.innerHTML)
			} else if (_actionTimerSeconds-- > 1) {
				button.innerHTML = _actionTimerLabel + ' (' + _actionTimerSeconds + ')'
				if (_actionTimerSeconds < 5 && !button.classList.contains('shake-bottom-infinite')) {
					button.classList.add('shake-bottom-infinite')
				}
			} else {
				window.clearInterval(this.actionTimerId)
				button.click()
				button.innerHTML = this.stripTime(button.innerHTML)
			}
		}
		actionTimerFunction()
		this.actionTimerId = window.setInterval(() => actionTimerFunction(), 1000)
	}

	public stopActionTimer() {
		if (this.actionTimerId) {
			window.clearInterval(this.actionTimerId)
			dojo.query('.timer-button').forEach((but: HTMLElement) => dojo.destroy(but.id))
			dojo.destroy(`cancel-button`)
			this.actionTimerId = undefined
		}
	}

	protected stripTime(buttonLabel: string): string {
		const regex = /\s*\([0-9]+\)$/
		return buttonLabel.replace(regex, '')
	}
}

// <reference path="../card-manager.ts"/>
class CardsManager extends CardsManagerBase<CardiaCard> {
	constructor(public game: CardiaGame, private deckNumber: number) {
		super(game, {
			animationManager: game.animationManager,
			getId: (card) => `cardia-card-${card.id}`,
			cardWidth: CARD_WIDTH,
			cardHeight: CARD_HEIGHT,
			setupDiv: (card: CardiaCard, div: HTMLElement) => {
				div.classList.add('cardia-card')
				div.dataset.cardId = '' + card.id
				div.dataset.cardType = '' + card.type
			},
			setupFrontDiv: (card: CardiaCard, div: HTMLElement) => {
				this.setFrontBackground(div as HTMLDivElement, card.type)

				const tokensId = `${super.getId(card)}-tokens`
				div.id = `${super.getId(card)}-front`

				//add help and update it regarding the card side
				const helpId = `${super.getId(card)}-front-info`
				const tooltipContent = this.getTooltip(card)
				if (!$(helpId)) {
					const info: HTMLDivElement = document.createElement('div')
					info.id = helpId
					info.innerText = '?'
					info.classList.add('css-icon', 'card-info')
					div.appendChild(info)
					this.game.addTooltipOnClickHelpButton(info.id, tooltipContent)
				}
				;(this.game as any).addTooltipHtml(div.id, tooltipContent)

				//adds tokens locations
				if (!$(tokensId)) {
					const container: HTMLDivElement = document.createElement('div')
					container.id = tokensId
					container.classList.add('tokens-location-wrapper')
					div.appendChild(container)

					const modifiers: HTMLDivElement = document.createElement('div')
					modifiers.id = `${super.getId(card)}-modifiers`
					modifiers.classList.add('card-modifiers')
					container.appendChild(modifiers)
					modifiers.innerHTML = `<div id="${super.getId(card)}-modifier-value"></div>`

					const ongoings: HTMLDivElement = document.createElement('div')
					ongoings.id = `${super.getId(card)}-ongoings`
					ongoings.classList.add('card-ongoings')
					container.appendChild(ongoings)

					const signets: HTMLDivElement = document.createElement('div')
					signets.id = `${super.getId(card)}-signets`
					signets.classList.add('card-signets')
					container.appendChild(signets)
				}
			},

			setupBackDiv: (card: CardiaCard, div: HTMLElement) => {
				this.setBackBackground(div as HTMLDivElement, card.type_arg, `${g_gamethemeurl}img/deckBacks.jpg`, 2)
			}
		})
	}

	public getCardName(card: CardiaCard) {
		return `<div class="cstm-card-name">${card.name}</div>`
	}

	public getTooltipContent(): TooltipElement[] {
		return [
			{ title: '', contentProvider: (c: CardiaCard) => this.getCardName(c) },
			{
				title: '',
				contentProvider: (c: CardiaCard) =>
					this.getPowerDesc(c) + '<br><br>' + this.getPowerTypeDesc(c.powerType) + '<br><br>'
			}
		]
	}

	private setFrontBackground(cardDiv: HTMLDivElement, cardType: number) {
		const imageUrl = `${g_gamethemeurl}img/deck${this.deckNumber}.jpg`
		cardDiv.style.backgroundImage = `url('${imageUrl}')`
		const imagePosition = Number(cardType.toString().slice(1)) - 1
		const row = Math.floor(imagePosition / IMAGE_ITEMS_PER_ROW)
		const xBackgroundPercent = (imagePosition - row * IMAGE_ITEMS_PER_ROW) * 100
		const yBackgroundPercent = row * 100
		cardDiv.style.backgroundPositionX = `-${xBackgroundPercent}%`
		cardDiv.style.backgroundPositionY = `-${yBackgroundPercent}%`
		cardDiv.style.backgroundSize = `${IMAGE_ITEMS_PER_ROW * 100}%`
	}

	private setBackBackground(cardDiv: HTMLDivElement, cardTypeArg: number, cardsUrl: string, imagesPerRow: number) {
		cardDiv.style.backgroundImage = `url('${cardsUrl}')`
		const imagePosition = cardTypeArg - 1
		const row = Math.floor(imagePosition / imagesPerRow)
		const xBackgroundPercent = (imagePosition - row * imagesPerRow) * 100
		const yBackgroundPercent = row * 100
		cardDiv.style.backgroundPositionX = `-${xBackgroundPercent}%`
		cardDiv.style.backgroundPositionY = `-${yBackgroundPercent}%`
		cardDiv.style.backgroundSize = `${imagesPerRow * 100}%`
	}

	public getPowerTypeDesc(powerType: PowerType) {
		switch (powerType) {
			case 'I':
				return _('<b>Instant ability</b>: When you activate an instant ability, resolve it immediately once.')
			case 'O':
				return _('<b>Ongoing ability</b>: The effect is enabled as long as there is an ongoing token on it.')
			default:
				return 'unexpected power type ' + powerType
		}
	}

	public getPowerDesc(card: CardiaCard) {
		switch (this.deckNumber) {
			case 1:
				switch (card.type) {
					case HIRED_BLADE:
						return _('Discard both this card and the opposing card.')
					case VOID_MAGE:
						return _('Remove all modifiers or all ongoing tokens from any one card.')
					case SURGEON:
						return _('Add -5 influence to the next card you play.')
					case MEDIATOR:
						return _('This encounter is a tie.')
					case SABOTEUR:
						return _('Your opponent discards the top 2 cards of their deck.')
					case FORTUNE_TELLER:
						return _(
							'In the next encounter, your opponent plays their card face up before you play your card.'
						)
					case PALACE_GUARD:
						return _(
							'Choose a faction. Your opponent may discard 1 card of the chosen faction from their hand. If they do not, add +7 influence to this card.'
						)
					case JUDGE:
						return _(
							'You win all tied encounters, including upcoming ones. <br>Ties do not trigger abilities.'
						)
					case AMBUSHER:
						return _(
							'Choose a faction. Your opponent discards all cards of the chosen faction from their hand.'
						)
					case PUPPETEER:
						return _(
							'Discard the opposing card. Replace it with a card you randomly draw from your opponent’s hand. <br>Its ability is not triggered.'
						)
					case CLOCKMAKER:
						return _(
							'Add +3 influence to your card in the previous encounter as well as to the next card you play.'
						)
					case TREASURER:
						return _('The card that wins the previous encounter is worth 1 additional signet.')
					case SWAMP_GUARDIAN:
						return _(
							'Take one of your other played cards back into your hand and discard its opposing card.'
						)
					case MAGISTRA:
						return _(
							'Copy and activate the instant ability of one of your other played cards with the same or higher influence than this card.'
						)
					case INVENTOR:
						return _('Add +3 influence to any one card and -3 influence to any other card.')
					case DJINN:
						return _('You win the game.')
					default:
						return 'unexpected card type in deck 1 ' + card.type
				}
			case 2:
				switch (card.type) {
					case POISONER:
						return _('Reduce the influence of the opposing card until this encounter is a tie.')
					case KINESIS_MAGE:
						return _(
							'Move all modifiers and ongoing tokens from one of your cards to any other of your cards.'
						)
					case ENVOY:
						return _('Add -3 influence to any one card, or to the next card you play.')
					case TAX_COLLECTOR:
						return _('Add +4 influence to this card.')
					case REVOLUTIONARY:
						return _('Your opponent discards 2 cards from their hand, then draws 2 cards.')
					case LIBRARIAN:
						return _('Add +2 or -2 influence to the next card you play after revealing it.')
					case PRODIGY:
						return _('Add +3 influence to any one of your cards with 8 or less influence.')
					case ARISTOCRAT:
						return _('If this card wins its encounter, it is worth an additional signet.')
					case BLACKMAILER:
						return _(
							'Choose a faction that your opponent may play next. If they do not, they discard 2 cards from their hand after revealing their card.'
						)
					case ILLUSIONIST:
						return _('Activate the ability of one of your losing cards.')
					case ENGINEER:
						return _('Add +5 influence to the next card you play, after triggering abilities.')
					case COUNSELOR:
						return _('In the previous encounter, your card wins and your opponent’s card loses.')
					case WITCH_KING:
						return _(
							'Choose a faction. Your opponent discards all cards of the chosen faction from their hand and deck. Then they shuffle their deck.'
						)
					case ELEMENTAL:
						return _(
							'Discard a card from your hand that has an instant ability. Copy and activate that ability, then draw a card.'
						)
					case MECHANICAL_DJINN:
						return _('If you win the following encounter, you win the game.')
					case SUCCESSOR:
						return _('Your opponent discards their entire deck and all but 2 cards from their hand.')
					default:
						return 'unexpected card type in deck 2 ' + card.type
				}
				break

			default:
				break
		}
	}
}

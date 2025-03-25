// <reference path="../card-manager.ts"/>
class CardsManager extends CardsManagerBase<CardiaCard> {
	constructor(public game: CardiaGame, private deckNumber: number) {
		super(game, {
			animationManager: game.animationManager,
			getId: (card) => `cardia-card-${card.id}`,
			setupDiv: (card: CardiaCard, div: HTMLElement) => {
				div.classList.add('cardia-card')
				div.dataset.cardId = '' + card.id
				div.dataset.cardType = '' + card.type
			},
			setupFrontDiv: (card: CardiaCard, div: HTMLElement) => {
				this.setFrontBackground(div as HTMLDivElement, card.type)

				const tokensId = `${super.getId(card)}-tokens`
				div.id = `${super.getId(card)}-front`

				//add help
				const helpId = `${super.getId(card)}-front-info`
				if (!$(helpId)) {
					const info: HTMLDivElement = document.createElement('div')
					info.id = helpId
					info.innerText = '?'
					info.classList.add('css-icon', 'card-info')
					div.appendChild(info)
					const tooltipContent = this.getTooltip(card)
					;(this.game as any).addTooltipHtml(info.id, tooltipContent)
					this.game.addTooltipOnClickHelpButton(info.id, tooltipContent)
				}

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

					const sigils: HTMLDivElement = document.createElement('div')
					sigils.id = `${super.getId(card)}-sigils`
					sigils.classList.add('card-sigils')
					container.appendChild(sigils)
				}
			},

			setupBackDiv: (card: CardiaCard, div: HTMLElement) => {
				div.style.backgroundImage = `url('${g_gamethemeurl}img/cardia-card-background.jpg')`
			}
		})
	}

	public getCardName(cardTypeId: number) {
		return 'todo'
	}

	public getTooltipContent(): TooltipElement[] {
		return [{ title: _('Objective'), contentProvider: (c: CardiaCard) => this.getDesc(c) }]
	}

	public getDesc(card: CardiaCard) {
		return 'todo'
	}

	private setFrontBackground(cardDiv: HTMLDivElement, cardType: number) {
		const imageUrl = `${g_gamethemeurl}img/deck${this.deckNumber}.jpg`
		cardDiv.style.backgroundImage = `url('${imageUrl}')`
		const imagePosition = cardType - 1
		const row = Math.floor(imagePosition / IMAGE_ITEMS_PER_ROW)
		const xBackgroundPercent = (imagePosition - row * IMAGE_ITEMS_PER_ROW) * 100
		const yBackgroundPercent = row * 100
		cardDiv.style.backgroundPositionX = `-${xBackgroundPercent}%`
		cardDiv.style.backgroundPositionY = `-${yBackgroundPercent}%`
		cardDiv.style.backgroundSize = `${IMAGE_ITEMS_PER_ROW * 100}%`
	}
}

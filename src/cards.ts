// <reference path="../card-manager.ts"/>
class CardsManager extends CardsManagerBase<CardiaCard> {
	constructor(public game: CardiaGame) {
		super(game, {
			animationManager: game.animationManager,
			getId: (card) => `cardia-card-${card.id}`,
			setupDiv: (card: CardiaCard, div: HTMLElement) => {
				div.classList.add('cardia-card')
				div.dataset.cardId = '' + card.id
				div.dataset.cardType = '' + card.type
			},
			setupFrontDiv: (card: CardiaCard, div: HTMLElement) => {
				this.setBackground(
					div as HTMLDivElement,
					card.type_arg,
					`${g_gamethemeurl}img/cardia-card-background.jpg`,
					IMAGE_ITEMS_PER_ROW
				)
				//this.setDivAsCard(div as HTMLDivElement, card.type);
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
}

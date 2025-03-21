interface Tooltipable {
	/**
	 * Return the list of elements that constitute the tooltip.
	 */
	getTooltipContent(): TooltipElement[]
}

/**
 * One element of information in the tooltip
 */
interface TooltipElement {
	/** Translated title of the element. Gives a h3 tag if some content is present. */
	title: string
	/** Method to get the content of the description. */
	contentProvider: (c: Card) => string
	/** Classes to add to the title. */
	classes?: string
}


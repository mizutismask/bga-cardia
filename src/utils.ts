function addTemporaryClass(element: HTMLElement | string, className: string, removalDelay: number) {
	dojo.addClass(element, className)
	setTimeout(() => dojo.removeClass(element, className), removalDelay)
}

function removeClass(className: string, rootNode?: HTMLElement | Document): void {
	if (!rootNode) rootNode = document
	else rootNode = rootNode as HTMLElement
	rootNode.querySelectorAll('.' + className).forEach((item) => item.classList.remove(className))
}

function queryFirst(query: string): Element {
	return document.querySelector(query)
}

function queryFirstId(query: string, defaultValue: string = undefined): string {
	var res = document.querySelector(query)
	if (!res) return defaultValue
	return res.id
}

/*
 * Detect if spectator or replay
 */
function isReadOnly() {
	return this.isSpectator || typeof (this as any).g_replayFrom != 'undefined' || (this as any).g_archive_mode
}

function getPart(haystack: string, i: number, noException: boolean = false, separator = '-'): string {
	const parts: string[] = haystack.split(separator)
	const len: number = parts.length

	if (noException && i >= len) {
		return ''
	}
	if (noException && len + i < 0) {
		return ''
	}
	return parts[i >= 0 ? i : len + i]
}

function replaceStarScoreIcon(newClass: string) {
	dojo.query('.fa-star')
		.removeClass('fa fa-star')
		.addClass(newClass)
		.style({ 'vertical-align': 'middle', 'display': 'inline-block' })
}

function createDiv(classes: string, id: string = '', value: string = '') {
	if (typeof value == 'undefined') value = ''
	const node: HTMLElement = dojo.create('div', { class: classes, innerHTML: value })
	if (id) node.id = id
	return node.outerHTML
}

function groupBy<T>(arr: T[], fn: (item: T) => any) {
	return arr.reduce<Record<string, T[]>>((prev, curr) => {
		const groupKey = fn(curr)
		const group = prev[groupKey] || []
		group.push(curr)
		return { ...prev, [groupKey]: group }
	}, {})
}

/**
 * sets by path blocks/end-time/ 797 bytes 2 assets
  assets by path blocks/venue-information/ 883 bytes 2 assets
  assets by path blocks/venue/ 706 bytes 2 assets
  assets by path blocks/event-date/ 641 bytes 2 assets
  assets by path blocks/add-to-calendar/ 688 bytes 2 assets
cached modules 195 KiB (javascript) 1.54 KiB (css/mini-extract) 12 KiB (runtime) [cached] 341 modules
runtime modules 3.56 KiB 6 modules
./src/blocks/time-template/pbrocks-sidebar.js 2.45 KiB [built] [code generated]
WARNING in ./src/blocks/time-template/pbrocks-sidebar.js 58:8-14
possible '@wordpress/icons' ( exports: Icon, addCard, addSubmenu, addTemplate, alignCenter, alignJustify, alignLeft, alignNone, alignRight, archive, arrowDown, arrowLeft, arrowRight, arrowUp, aspectRatio, atSymbol, audio, backup, blockDefault, blockMeta, blockTable, box, brush, bug, button, buttons, calendar, cancelCircleFilled, caption, capturePhoto, captureVideo, category, chartBar, check, chevronDown, chevronLeft, chevronLeftSmall, chevronRight, chevronRightSmall, chevronUp, chevronUpDown, classic, close, closeSmall, cloud, cloudUpload, code, cog, color, column, columns, comment, commentAuthorAvatar, commentAuthorName, commentContent, commentEditLink, commentReplyLink, copy, cover, create, crop, currencyDollar, currencyEuro, currencyPound, customLink, customPostType, desktop, download, dragHandle, edit, external, file, filter, flipHorizontal, flipVertical, footer, formatBold, formatCapitalize, formatIndent, formatIndentRTL, formatItalic, formatListBullets, formatListBulletsRTL, formatListNumbered, formatListNumberedRTL, formatLowercase, formatLtr, formatOutdent, formatOutdentRTL, formatRtl, formatStrikethrough, formatUnderline, formatUppercase, fullscreen, gallery, globe, grid, group, handle, header, heading, help, helpFilled, home, html, image, inbox, info, insertAfter, insertBefore, institution, justifyCenter, justifyLeft, justifyRight, justifySpaceBetween, key, keyboardClose, keyboardReturn, layout, lifesaver, lineDashed, lineDotted, lineSolid, link, linkOff, list, listItem, listView, lock, login, loop, mapMarker, media, mediaAndText, megaphone, menu, mobile, more, moreHorizontal, moreHorizontalMobile, moreVertical, moveTo, navigation, next, notFound, overlayText, page, pageBreak, pages, paragraph, payment, pencil, people, percent, pin, plugins, plus, plusCircle, plusCircleFilled, positionCenter, positionLeft, positionRight, post, postAuthor, postCategories, postComments, postCommentsCount, postCommentsForm, postContent, postDate, postExcerpt, postFeaturedImage, postList, postTerms, preformatted, previous, pullLeft, pullRight, pullquote, queryPagination, queryPaginationNext, queryPaginationNumbers, queryPaginationPrevious, quote, receipt, redo, removeBug, removeSubmenu, replace, reset, resizeCornerNE, reusableBlock, rotateLeft, rotateRight, row, rss, search, separator, settings, share, shield, shipping, shortcode, shuffle, sidebar, siteLogo, stack, starEmpty, starFilled, starHalf, store, stretchFullWidth, stretchWide, styles, subscript, superscript, swatch, symbol, symbolFilled, table, tableColumnAfter, tableColumnBefore, tableColumnDelete, tableRowAfter, tableRowBefore, tableRowDelete, tablet, tag, termDescription, textColor, tip, title, tool, trash, trendingDown, trendingUp, typography, undo, ungroup, unlock, update, upload, verse, video, warning, widget, wordpress
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/edit-post';
import { image, institution, check, pencil } from '@wordpress/icons';

import { Panel, PanelBody, PanelRow } from '@wordpress/components';

const MyPanel = () => (
	<Panel header="My Panel">
		<PanelBody title="My Block Settings" icon={more} initialOpen={true}>
			<PanelRow>My Panel Inputs and Labels</PanelRow>
		</PanelBody>
	</Panel>
);
import { TabPanel } from '@wordpress/components';

const onSelect = (tabName) => {
	console.log('Selecting tab ', tabName);
};

const MyTabPanel = () => (
	<TabPanel
		className="my-tab-panel"
		activeClass="active-tab"
		onSelect={onSelect}
		tabs={[
			{
				name: 'primary',
				title: 'Tab 1 Title',
				content: 'Tab 1 Content is kind of like paragraph information.',
				className: 'tab-one is-primary',
			},
			{
				name: 'tab2',
				title: 'Tab 2 Title',
				content: 'Tab 2 Content is kind of like paragraph information.',
				className: 'tab-two is-secondary',
				variant: 'secondary',
			},
			{
				name: 'tab3',
				title: 'Tab 3 Title',
				content: 'Tab 3 Content is kind of like paragraph information.',
				className: 'tab-three is-secondary',
			},
		]}
	>
		{(tab) => (
			<>
				<h3>{tab.title}</h3>
				<p>{tab.content}</p>
			</>
		)}
	</TabPanel>
);

const PBrocksSettingsSidebar = () => (
	<PluginSidebar name="pbrocks-settings-sidebar" title="GatherPress Event" icon="nametag">
		<PanelBody title="Settings PanelBody" icon={check} initialOpen={false}>
			<PanelRow>
				Settings PanelRow within PanelBody
			</PanelRow>
			<PanelRow>
				Settings PanelRow within PanelBody
			</PanelRow>
		</PanelBody>
		<PanelBody title="Venue PanelBody" icon={pencil} initialOpen={false}>
			<PanelRow>
				Venue PanelRow within PanelBody
			</PanelRow>
		</PanelBody>
		<PanelBody title="Topics PanelBody" icon={institution} initialOpen={false}>
			<PanelRow>
				Topics PanelRow within PanelBody
			</PanelRow>
		</PanelBody>
		<PanelBody title="Attendance PanelBody" icon="palmtree" initialOpen={false}>
			<PanelRow>
				Attendance PanelRow within PanelBody
			</PanelRow>
		</PanelBody>
		<PanelBody title="Tabbed PanelBody" icon={image} initialOpen={true}>
			<PanelRow>
				<MyTabPanel />
			</PanelRow>
		</PanelBody>
	</PluginSidebar>
);

registerPlugin('pbrocks-settings-sidebar', { render: PBrocksSettingsSidebar });

export default PluginSidebar

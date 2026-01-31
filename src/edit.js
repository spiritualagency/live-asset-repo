/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

import { PanelBody, Button, Notice, Spinner, TabPanel } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { copy, download, update } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit() {
	const blockProps = useBlockProps();
	const [plugins, setPlugins] = useState([]);
	const [themes, setThemes] = useState([]);
	const [updateLog, setUpdateLog] = useState([]);
	const [loading, setLoading] = useState(true);
	const [regenerating, setRegenerating] = useState(false);
	const [error, setError] = useState(null);
	const [copiedUrl, setCopiedUrl] = useState(null);

	const fetchItems = () => {
		setLoading(true);
		setError(null);

		apiFetch({ path: '/pptd/v1/items' })
			.then((data) => {
				setPlugins(data.plugins || []);
				setThemes(data.themes || []);
				setLoading(false);
			})
			.catch((err) => {
				setError(err.message || __('Failed to load plugins and themes', 'permanent-plugin-theme-downloader'));
				setLoading(false);
			});
	};

	const fetchLog = () => {
		apiFetch({ path: '/pptd/v1/log' })
			.then((data) => {
				setUpdateLog(data.log || []);
			})
			.catch((err) => {
				console.error('Failed to load update log:', err);
			});
	};

	const regenerateZips = () => {
		setRegenerating(true);
		setError(null);

		apiFetch({ 
			path: '/pptd/v1/regenerate',
			method: 'POST'
		})
			.then(() => {
				setRegenerating(false);
				fetchItems();
				fetchLog();
			})
			.catch((err) => {
				setError(err.message || __('Failed to regenerate ZIP files', 'permanent-plugin-theme-downloader'));
				setRegenerating(false);
			});
	};

	useEffect(() => {
		fetchItems();
		fetchLog();
	}, []);

	const copyToClipboard = (url) => {
		if (typeof url !== 'string' || !url) {
			setError(__('Invalid URL', 'permanent-plugin-theme-downloader'));
			return;
		}

		try {
			const textArea = document.createElement('textarea');
			textArea.value = url;
			textArea.style.position = 'fixed';
			textArea.style.left = '-9999px';
			textArea.style.top = '-9999px';
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();
			
			const successful = document.execCommand('copy');
			document.body.removeChild(textArea);
			
			if (successful) {
				setCopiedUrl(url);
				setError(null);
				setTimeout(() => setCopiedUrl(null), 2000);
			} else {
				setError(__('Unable to copy URL. Please copy it manually.', 'permanent-plugin-theme-downloader'));
			}
		} catch (err) {
			console.error('Copy failed:', err);
			setError(__('Unable to copy URL. Please copy it manually.', 'permanent-plugin-theme-downloader'));
		}
	};

	const formatDate = (timestamp) => {
		const date = new Date(timestamp);
		return date.toLocaleString();
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Settings', 'permanent-plugin-theme-downloader')}>
					<Button 
						variant="secondary" 
						onClick={fetchItems} 
						disabled={loading || regenerating}
						style={{ marginBottom: '12px', width: '100%' }}
					>
						{loading ? __('Refreshing...', 'permanent-plugin-theme-downloader') : __('Refresh List', 'permanent-plugin-theme-downloader')}
					</Button>
					<Button 
						variant="primary" 
						onClick={regenerateZips} 
						disabled={loading || regenerating}
						style={{ width: '100%' }}
					>
						{regenerating ? __('Regenerating...', 'permanent-plugin-theme-downloader') : __('Regenerate All ZIPs', 'permanent-plugin-theme-downloader')}
					</Button>
					<p style={{ marginTop: '12px', fontSize: '13px', color: '#757575' }}>
						{__('ZIP files are automatically created and stored in wp-content/pptd-downloads/. They update automatically when plugins or themes are updated. Click "Regenerate" to manually update all ZIP files.', 'permanent-plugin-theme-downloader')}
					</p>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="pptd-header">
					<h3>{__('Permanent Download URLs', 'permanent-plugin-theme-downloader')}</h3>
					<p className="pptd-description">
						{__('ZIP files are stored in wp-content/pptd-downloads/ with permanent URLs that automatically serve the latest version when updated.', 'permanent-plugin-theme-downloader')}
					</p>
					{copiedUrl && (
						<Notice status="success" isDismissible={false} className="pptd-notice">
							{__('URL copied to clipboard!', 'permanent-plugin-theme-downloader')}
						</Notice>
					)}
				</div>

				{(loading || regenerating) && (
					<div className="pptd-loading">
						<Spinner />
						<p>
							{regenerating 
								? __('Regenerating ZIP files...', 'permanent-plugin-theme-downloader')
								: __('Loading plugins and themes...', 'permanent-plugin-theme-downloader')
							}
						</p>
					</div>
				)}

				{error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}

				{!loading && !regenerating && !error && (
					<TabPanel
						className="pptd-tabs"
						activateByIndex={0}
						tabs={[
							{
								name: 'downloads',
								title: __('Downloads', 'permanent-plugin-theme-downloader'),
								className: 'pptd-tab-downloads',
							},
							{
								name: 'log',
								title: __('Update Log', 'permanent-plugin-theme-downloader'),
								className: 'pptd-tab-log',
							},
						]}
					>
						{(tab) => (
							<div className="pptd-tab-content">
								{tab.name === 'downloads' && (
									<>
										<div className="pptd-section">
											<h4 className="pptd-section-title">
												{__('Plugins', 'permanent-plugin-theme-downloader')} ({plugins.length})
											</h4>
											{plugins.length === 0 ? (
												<p className="pptd-empty">{__('No plugins found.', 'permanent-plugin-theme-downloader')}</p>
											) : (
												<div className="pptd-list">
													{plugins.map((plugin) => (
														<div key={plugin.slug} className="pptd-item">
															<div className="pptd-item-info">
																<strong className="pptd-item-name">{plugin.name}</strong>
																<span className="pptd-item-version">v{plugin.version}</span>
																{plugin.zip_exists ? (
																	<code className="pptd-item-url">{plugin.url}</code>
																) : (
																	<span className="pptd-item-error">{__('ZIP creation failed', 'permanent-plugin-theme-downloader')}</span>
																)}
															</div>
															<div className="pptd-item-actions">
																{plugin.zip_exists && (
																	<>
																		<Button
																			icon={copy}
																			label={__('Copy URL', 'permanent-plugin-theme-downloader')}
																			onClick={() => copyToClipboard(plugin.url)}
																			className={copiedUrl === plugin.url ? 'pptd-copied' : ''}
																		/>
																		<Button
																			icon={download}
																			label={__('Download', 'permanent-plugin-theme-downloader')}
																			href={plugin.url}
																			download
																		/>
																	</>
																)}
															</div>
														</div>
													))}
												</div>
											)}
										</div>

										<div className="pptd-section">
											<h4 className="pptd-section-title">
												{__('Themes', 'permanent-plugin-theme-downloader')} ({themes.length})
											</h4>
											{themes.length === 0 ? (
												<p className="pptd-empty">{__('No themes found.', 'permanent-plugin-theme-downloader')}</p>
											) : (
												<div className="pptd-list">
													{themes.map((theme) => (
														<div key={theme.slug} className="pptd-item">
															<div className="pptd-item-info">
																<strong className="pptd-item-name">{theme.name}</strong>
																<span className="pptd-item-version">v{theme.version}</span>
																{theme.zip_exists ? (
																	<code className="pptd-item-url">{theme.url}</code>
																) : (
																	<span className="pptd-item-error">{__('ZIP creation failed', 'permanent-plugin-theme-downloader')}</span>
																)}
															</div>
															<div className="pptd-item-actions">
																{theme.zip_exists && (
																	<>
																		<Button
																			icon={copy}
																			label={__('Copy URL', 'permanent-plugin-theme-downloader')}
																			onClick={() => copyToClipboard(theme.url)}
																			className={copiedUrl === theme.url ? 'pptd-copied' : ''}
																		/>
																		<Button
																			icon={download}
																			label={__('Download', 'permanent-plugin-theme-downloader')}
																			href={theme.url}
																			download
																		/>
																	</>
																)}
															</div>
														</div>
													))}
												</div>
											)}
										</div>
									</>
								)}

								{tab.name === 'log' && (
									<div className="pptd-log-section">
										<div className="pptd-log-header">
											<h4>{__('Update History', 'permanent-plugin-theme-downloader')}</h4>
											<p className="pptd-log-description">
												{__('Automatic log of all plugin and theme updates. ZIP files are regenerated each time an update occurs.', 'permanent-plugin-theme-downloader')}
											</p>
										</div>
										{updateLog.length === 0 ? (
											<p className="pptd-empty">{__('No updates recorded yet.', 'permanent-plugin-theme-downloader')}</p>
										) : (
											<div className="pptd-log-list">
												{updateLog.map((entry, index) => (
													<div key={index} className="pptd-log-entry">
														<div className="pptd-log-icon">
															{entry.type === 'plugin' ? '🔌' : '🎨'}
														</div>
														<div className="pptd-log-info">
															<div className="pptd-log-name">
																<strong>{entry.name}</strong>
																<span className="pptd-log-type">{entry.type}</span>
															</div>
															<div className="pptd-log-versions">
																<span className="pptd-log-old-version">v{entry.old_version}</span>
																<span className="pptd-log-arrow">→</span>
																<span className="pptd-log-new-version">v{entry.new_version}</span>
															</div>
															<div className="pptd-log-timestamp">{formatDate(entry.timestamp)}</div>
														</div>
													</div>
												))}
											</div>
										)}
									</div>
								)}
							</div>
						)}
					</TabPanel>
				)}
			</div>
		</>
	);
}
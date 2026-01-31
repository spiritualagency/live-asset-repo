
/**
 * Frontend JavaScript for the Permanent Plugin Theme Downloader block.
 */

document.addEventListener('DOMContentLoaded', function() {
	const downloadLinks = document.querySelectorAll('.pptd-download-link');
	
	downloadLinks.forEach(link => {
		link.addEventListener('click', function(e) {
			// Allow the default download behavior
			// Add visual feedback
			const originalText = this.textContent;
			this.textContent = '⬇ Downloading...';
			
			setTimeout(() => {
				this.textContent = originalText;
			}, 2000);
		});
	});
});
	
<?php
// This view is included by index.php, which handles the main HTML structure,
// <head> (including CSS links), and <header>.
$guideSlug = htmlspecialchars($_GET['slug'] ?? '');
?>
<main class="guide-detail-container">
    <h1 id="guideDetailTitle">Loading guide...</h1>
    <p id="guideDetailMeta"></p>
    <article id="guideDetailContent">
        <p class="loading-text">Loading content...</p>
    </article>
</main>

<script>
    const guideControllerUrl = '/src/Controllers/GuideController.php';
    const guideSlug = '<?php echo $guideSlug; ?>';

    const guideDetailTitle = document.getElementById('guideDetailTitle');
    const guideDetailMeta = document.getElementById('guideDetailMeta');
    const guideDetailContent = document.getElementById('guideDetailContent');

    async function fetchGuideDetail() {
        if (!guideSlug) {
            guideDetailTitle.textContent = 'Guide Not Found';
            guideDetailContent.innerHTML = '<p class="error-text">No guide specified in the URL.</p>';
            guideDetailMeta.textContent = '';
            return;
        }

        try {
            // Construct the URL with query parameters
            const fetchUrl = `${guideControllerUrl}?action=getGuide&data[slug]=${encodeURIComponent(guideSlug)}`;
            
            const response = await fetch(fetchUrl);
            
            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.message || errorMessage;
                } catch (e) {
                    // Could not parse JSON, use default HTTP error
                }
                throw new Error(errorMessage);
            }

            const result = await response.json();

            if (result.status === 'success' && result.data) {
                const guide = result.data;
                // Dynamically update the page title if possible (might require DOM manipulation outside this script if title tag is not accessible)
                // document.title = guide.title + " - Aternotes Guides"; // This line will work if script is before </head>
                guideDetailTitle.textContent = guide.title;
                
                const publishedDate = guide.published_at ? new Date(guide.published_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Not available';
                guideDetailMeta.innerHTML = `By <span class="author">${guide.author_username || 'Unknown Author'}</span> | Published: ${publishedDate}`;
                
                // Ensure HTML content is rendered as HTML, not text
                guideDetailContent.innerHTML = guide.content_html; 
            } else {
                throw new Error(result.message || 'Guide data not found.');
            }
        } catch (error) {
            console.error('Error fetching guide detail:', error);
            guideDetailTitle.textContent = 'Guide Not Found';
            guideDetailContent.innerHTML = `<p class="error-text">Could not load guide: ${error.message}</p>`;
            guideDetailMeta.textContent = '';
        }
    }

    document.addEventListener('DOMContentLoaded', fetchGuideDetail);
</script>

<?php
// Footer and closing tags are handled by index.php
?>

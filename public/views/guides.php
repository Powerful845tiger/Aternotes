<?php
// This view is included by index.php, which handles the main HTML structure,
// <head> (including CSS links), and <header>.
?>
<main class="guides-container">
    <h1>Guides</h1>
    <div id="publishedGuidesListContainer">
        <p class="loading-text">Loading guides...</p>
        <!-- Guides will be loaded here by JavaScript -->
    </div>
</main>

<script>
    const guideControllerUrl = '/src/Controllers/GuideController.php';
    const publishedGuidesListContainer = document.getElementById('publishedGuidesListContainer');

    async function fetchPublishedGuides() {
        try {
            const response = await fetch(`${guideControllerUrl}?action=listPublishedGuides`);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: 'Failed to fetch guides. Server returned an error.' }));
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            const result = await response.json();

            if (result.status === 'success' && Array.isArray(result.data)) {
                publishedGuidesListContainer.innerHTML = ''; // Clear loading message
                if (result.data.length === 0) {
                    publishedGuidesListContainer.innerHTML = '<p>No guides published yet. Check back soon!</p>';
                    return;
                }
                result.data.forEach(guide => {
                    const guideItem = document.createElement('div');
                    guideItem.classList.add('guide-item');

                    const titleLink = document.createElement('a');
                    titleLink.href = `/guide/${guide.slug}`;
                    titleLink.textContent = guide.title;

                    const titleHeader = document.createElement('h2');
                    titleHeader.appendChild(titleLink);

                    const meta = document.createElement('p');
                    meta.classList.add('meta');
                    // Format date nicely
                    const publishedDate = guide.published_at ? new Date(guide.published_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                    meta.textContent = `By ${guide.author_username || 'Unknown Author'} | Published: ${publishedDate}`;
                    
                    // Optionally, add a snippet if available in the response
                    // const snippetPara = document.createElement('p');
                    // snippetPara.textContent = guide.snippet || ''; // Assuming 'snippet' field from controller

                    guideItem.appendChild(titleHeader);
                    guideItem.appendChild(meta);
                    // guideItem.appendChild(snippetPara); 

                    publishedGuidesListContainer.appendChild(guideItem);
                });
            } else {
                throw new Error(result.message || 'Failed to load published guides.');
            }
        } catch (error) {
            console.error('Error fetching published guides:', error);
            publishedGuidesListContainer.innerHTML = `<p class="error-text">Could not load guides: ${error.message}</p>`;
        }
    }

    document.addEventListener('DOMContentLoaded', fetchPublishedGuides);
</script>

<?php
// Footer and closing tags are handled by index.php
?>

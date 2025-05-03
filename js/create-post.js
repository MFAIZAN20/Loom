document.addEventListener('DOMContentLoaded', function() {
    const postForm = document.getElementById('post-form');
    const titleField = document.getElementById('post-title');
    const contentField = document.getElementById('post-content');
    const submitButton = document.getElementById('post-submit');
    const analysisContainer = document.getElementById('post-analysis');
    
    if (postForm) {
        // Show loading indicator when AI is initializing
        if (!window.postAnalyzer || !window.postAnalyzer.isReady) {
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'ai-loading';
            loadingIndicator.innerHTML = `
                <i class="fas fa-cog fa-spin"></i>
                <span>AI content analyzer is initializing...</span>
            `;
            analysisContainer.appendChild(loadingIndicator);
            
            // Listen for AI ready event
            document.addEventListener('post-analyzer-ready', function() {
                analysisContainer.removeChild(loadingIndicator);
            });
            
            // Listen for AI failure
            document.addEventListener('post-analyzer-failed', function() {
                loadingIndicator.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <span>AI analyzer couldn't be loaded. Your post will be submitted normally.</span>
                `;
            });
        }
        
        // Live analysis as user types (with debounce)
        let analysisTimeout = null;
        
        function updateAnalysis() {
            if (analysisTimeout) {
                clearTimeout(analysisTimeout);
            }
            
            analysisTimeout = setTimeout(async function() {
                const title = titleField.value.trim();
                const content = contentField.value.trim();
                
                // Skip analysis for empty content
                if (title.length < 5 || content.length < 10) {
                    analysisContainer.innerHTML = '';
                    return;
                }
                
                // Show loading state
                analysisContainer.innerHTML = `
                    <div class="analysis-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Analyzing your post...</span>
                    </div>
                `;
                
                // Analyze the post
                if (window.postAnalyzer && window.postAnalyzer.isReady) {
                    try {
                        const analysis = await window.postAnalyzer.analyzePost(title, content);
                        
                        if (analysis.success) {
                            // Show analysis results
                            displayAnalysisResult(analysis);
                            
                            // Add hidden field with karma adjustment
                            let karmaField = document.getElementById('karma-adjustment');
                            if (!karmaField) {
                                karmaField = document.createElement('input');
                                karmaField.type = 'hidden';
                                karmaField.name = 'karma_adjustment';
                                karmaField.id = 'karma-adjustment';
                                postForm.appendChild(karmaField);
                            }
                            karmaField.value = analysis.karma_change;
                            
                            // Add analysis data as JSON
                            let analysisDataField = document.getElementById('analysis-data');
                            if (!analysisDataField) {
                                analysisDataField = document.createElement('input');
                                analysisDataField.type = 'hidden';
                                analysisDataField.name = 'analysis_data';
                                analysisDataField.id = 'analysis-data';
                                postForm.appendChild(analysisDataField);
                            }
                            analysisDataField.value = JSON.stringify(analysis);
                        } else {
                            // Show error
                            analysisContainer.innerHTML = `
                                <div class="analysis-error">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>${analysis.message}</span>
                                </div>
                            `;
                        }
                    } catch (error) {
                        console.error("Error in post analysis:", error);
                        analysisContainer.innerHTML = '';
                    }
                }
            }, 1000); // Wait for 1 second after typing stops
        }
        
        // Display the analysis result
        function displayAnalysisResult(analysis) {
            // Determine color class based on karma change
            let colorClass = 'neutral';
            if (analysis.karma_change > 0) {
                colorClass = 'positive';
            } else if (analysis.karma_change < 0) {
                colorClass = 'negative';
            }
            
            // Create analysis display
            analysisContainer.innerHTML = `
                <div class="content-analysis ${colorClass}">
                    <div class="analysis-header">
                        <i class="fas fa-robot"></i>
                        <h4>AI Content Analysis</h4>
                    </div>
                    
                    <div class="analysis-summary">
                        ${analysis.analysis_summary}
                    </div>
                    
                    <div class="analysis-karma">
                        Estimated karma adjustment: 
                        <span class="karma-value ${colorClass}">
                            ${analysis.karma_change >= 0 ? '+' : ''}${analysis.karma_change}
                        </span>
                    </div>
                    
                    <div class="analysis-quality">
                        <div class="quality-label">Content quality: <strong>${analysis.quality_level}</strong></div>
                        <div class="quality-meter">
                            <div class="quality-fill" style="width: ${Math.round(analysis.quality_score * 100)}%"></div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Attach event listeners for live analysis
        titleField.addEventListener('input', updateAnalysis);
        contentField.addEventListener('input', updateAnalysis);
        
        // Form submission handler
        postForm.addEventListener('submit', async function(event) {
            // If AI analysis is available but hasn't been run yet, analyze now
            if (window.postAnalyzer && window.postAnalyzer.isReady && !document.getElementById('karma-adjustment')) {
                event.preventDefault();
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
                
                const title = titleField.value.trim();
                const content = contentField.value.trim();
                
                try {
                    // Do a final analysis
                    const analysis = await window.postAnalyzer.analyzePost(title, content);
                    
                    // Add karma adjustment as hidden field
                    const karmaField = document.createElement('input');
                    karmaField.type = 'hidden';
                    karmaField.name = 'karma_adjustment';
                    karmaField.value = analysis.success ? analysis.karma_change : 0;
                    postForm.appendChild(karmaField);
                    
                    // Add analysis data as JSON
                    const analysisDataField = document.createElement('input');
                    analysisDataField.type = 'hidden';
                    analysisDataField.name = 'analysis_data';
                    analysisDataField.value = JSON.stringify(analysis);
                    postForm.appendChild(analysisDataField);
                    
                    // Submit the form
                    postForm.submit();
                } catch (error) {
                    console.error("Error analyzing post for submission:", error);
                    // Submit anyway
                    postForm.submit();
                }
            }
        });
    }
});
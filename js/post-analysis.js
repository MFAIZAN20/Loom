class PostAnalyzer {
    constructor() {
        this.toxicityModel = null;
        this.sentimentModel = null;
        this.isLoading = false;
        this.isReady = false;
        
        // Load models in background
        this.loadModels();
    }
    
    async loadModels() {
        try {
            this.isLoading = true;
            
            console.log("Loading toxicity model...");
            this.toxicityModel = await toxicity.load(0.7, ['toxicity', 'severe_toxicity', 'identity_attack', 'insult', 'threat']);
            
            console.log("Loading text encoder model...");
            this.sentimentModel = await use.load();
            
            this.isLoading = false;
            this.isReady = true;
            console.log("AI models loaded successfully");
            
            document.dispatchEvent(new CustomEvent('post-analyzer-ready'));
        } catch (error) {
            console.error("Error loading AI models:", error);
            this.isLoading = false;
            
            document.dispatchEvent(new CustomEvent('post-analyzer-failed'));
        }
    }
    
    /**
     * Analyze post content and calculate karma adjustment
     * @param {string} title - Post title
     * @param {string} content - Post content
     * @returns {Promise<Object>} Analysis results including karma adjustment
     */
    async analyzePost(title, content) {
        if (!this.isReady && !this.isLoading) {
            await this.loadModels();
        } else if (this.isLoading) {
            return { 
                success: false, 
                message: "AI models are still loading. Please try again in a moment." 
            };
        }
        
        try {
            const fullText = title + " " + content;
            
            // Check for toxic content
            const toxicityResults = await this.toxicityModel.classify(fullText);
            
            // Extract toxicity scores
            const toxicityData = {};
            let hasToxicity = false;
            let highestToxicity = 0;
            
            toxicityResults.forEach(result => {
                const label = result.label;
                const match = result.results[0].match;
                const score = result.results[0].probabilities[1]; // Probability of being toxic
                
                toxicityData[label] = {
                    detected: match,
                    score: score
                };
                
                if (match) {
                    hasToxicity = true;
                }
                
                highestToxicity = Math.max(highestToxicity, score);
            });
            
            const embeddings = await this.sentimentModel.embed([
                fullText,
                "This is a high quality, helpful post",
                "This is a neutral, basic post",
                "This is a low quality, unhelpful post"
            ]);
            
            const text_embedding = embeddings.arraySync()[0];
            const positive_embedding = embeddings.arraySync()[1];
            const neutral_embedding = embeddings.arraySync()[2];
            const negative_embedding = embeddings.arraySync()[3];
            
            // Calculate cosine similarity with each reference
            const positive_score = this.cosineSimilarity(text_embedding, positive_embedding);
            const neutral_score = this.cosineSimilarity(text_embedding, neutral_embedding);
            const negative_score = this.cosineSimilarity(text_embedding, negative_embedding);
            
            // Calculate quality score (normalized to 0-1)
            const qualityScore = (positive_score - negative_score + 1) / 2;
            
            // Calculate karma adjustment based on analysis
            let karmaChange = 0;
            let qualityLevel = '';
            
            if (hasToxicity && highestToxicity > 0.8) {
                // Highly toxic content
                karmaChange = -10;
                qualityLevel = 'very poor';
            } else if (hasToxicity) {
                // Somewhat toxic content
                karmaChange = -5;
                qualityLevel = 'poor';
            } else if (qualityScore > 0.8) {
                // High quality content
                karmaChange = 10;
                qualityLevel = 'excellent';
            } else if (qualityScore > 0.6) {
                // Good content
                karmaChange = 5;
                qualityLevel = 'good';
            } else if (qualityScore > 0.4) {
                // Average content
                karmaChange = 2;
                qualityLevel = 'average';
            } else {
                // Below average, but not toxic
                karmaChange = 0;
                qualityLevel = 'below average';
            }
            
            return {
                success: true,
                karma_change: karmaChange,
                quality_level: qualityLevel,
                quality_score: qualityScore,
                toxicity: toxicityData,
                sentiment: {
                    positive_score: positive_score,
                    neutral_score: neutral_score,
                    negative_score: negative_score
                },
                analysis_summary: this.getAnalysisSummary(qualityScore, karmaChange, hasToxicity)
            };
            
        } catch (error) {
            console.error("Error analyzing post:", error);
            return {
                success: false,
                message: "Could not analyze post content. Defaulting to standard post."
            };
        }
    }
    
    cosineSimilarity(a, b) {
        let dotProduct = 0;
        let normA = 0;
        let normB = 0;
        
        for (let i = 0; i < a.length; i++) {
            dotProduct += a[i] * b[i];
            normA += a[i] * a[i];
            normB += b[i] * b[i];
        }
        
        normA = Math.sqrt(normA);
        normB = Math.sqrt(normB);
        
        return dotProduct / (normA * normB);
    }
    
    getAnalysisSummary(qualityScore, karmaChange, hasToxicity) {
        if (hasToxicity) {
            return "This post may contain content that doesn't meet our community guidelines. Consider revising for a more positive contribution.";
        }
        
        if (qualityScore > 0.8) {
            return "Excellent post! This high-quality content is helpful to our community.";
        } else if (qualityScore > 0.6) {
            return "Good post. Your contribution adds value to our community.";
        } else if (qualityScore > 0.4) {
            return "Your post meets our community standards.";
        } else {
            return "Consider adding more details or information to improve your post quality.";
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Create global post analyzer instance
    window.postAnalyzer = new PostAnalyzer();
});
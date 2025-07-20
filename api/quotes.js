// api/quotes.js
export default async function handler(req, res) {
    // Enable CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    
    // Handle preflight requests
    if (req.method === 'OPTIONS') {
        res.status(200).end();
        return;
    }
    
    if (req.method !== 'GET') {
        return res.status(405).json({ error: 'Method not allowed' });
    }
    
    try {
        const NOTION_API_KEY = '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';
        const DATABASE_ID = '23595ea398ae80d9997bc9fa50adf47e';
        
        const response = await fetch(`https://api.notion.com/v1/databases/${DATABASE_ID}/query`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${NOTION_API_KEY}`,
                'Notion-Version': '2022-06-28',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                filter: {
                    property: 'Status',
                    select: {
                        equals: 'Active'
                    }
                }
            })
        });
        
        if (!response.ok) {
            throw new Error(`Notion API error: ${response.status}`);
        }
        
        const data = await response.json();
        
        const quotes = data.results.map(page => ({
            text: page.properties.Quote?.rich_text?.[0]?.plain_text || '',
            author: page.properties.Author?.rich_text?.[0]?.plain_text || '',
            occupation: page.properties.Occupation?.rich_text?.[0]?.plain_text || '',
            source: page.properties.Source?.rich_text?.[0]?.plain_text || ''
        })).filter(quote => quote.text.trim());
        
        res.status(200).json({ quotes });
        
    } catch (error) {
        console.error('Error fetching quotes:', error);
        res.status(500).json({ 
            error: 'Failed to fetch quotes',
            message: error.message 
        });
    }
}

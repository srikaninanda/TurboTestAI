// Dashboard-specific JavaScript functionality

const Dashboard = {
    charts: {},
    
    init() {
        this.loadStats();
        this.initializeCharts();
        this.loadRecentActivity();
        this.setupRefreshTimer();
    },
    
    async loadStats() {
        try {
            // Simulate API call - in real implementation, this would fetch from PHP endpoint
            const stats = await this.fetchStats();
            this.updateStatCards(stats);
        } catch (error) {
            console.error('Failed to load stats:', error);
        }
    },
    
    async fetchStats() {
        // In a real implementation, this would be an AJAX call to a PHP endpoint
        // For now, we'll get the values from the DOM or use placeholder data
        const requirements = document.getElementById('total-requirements')?.textContent || '0';
        const testcases = document.getElementById('total-testcases')?.textContent || '0';
        const bugs = document.getElementById('total-bugs')?.textContent || '0';
        
        return {
            requirements: parseInt(requirements),
            testcases: parseInt(testcases),
            bugs: parseInt(bugs),
            testExecution: {
                passed: 45,
                failed: 12,
                blocked: 3,
                not_run: 15
            },
            bugStatus: {
                open: 8,
                in_progress: 4,
                resolved: 23,
                closed: 15
            }
        };
    },
    
    updateStatCards(stats) {
        const elements = {
            'total-requirements': stats.requirements,
            'total-testcases': stats.testcases,
            'total-bugs': stats.bugs
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element && element.textContent === '-') {
                this.animateNumber(element, 0, value, 1000);
            }
        });
    },
    
    animateNumber(element, start, end, duration) {
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = Math.floor(start + (end - start) * progress);
            element.textContent = current;
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    },
    
    initializeCharts() {
        this.createTestExecutionChart();
        this.createBugStatusChart();
    },
    
    createTestExecutionChart() {
        const ctx = document.getElementById('testExecutionChart');
        if (!ctx) return;
        
        this.charts.testExecution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Passed', 'Failed', 'Blocked', 'Not Run'],
                datasets: [{
                    data: [45, 12, 3, 15],
                    backgroundColor: [
                        '#198754', // Success - Passed
                        '#dc3545', // Danger - Failed
                        '#ffc107', // Warning - Blocked
                        '#6c757d'  // Secondary - Not Run
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    },
    
    createBugStatusChart() {
        const ctx = document.getElementById('bugStatusChart');
        if (!ctx) return;
        
        this.charts.bugStatus = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Open', 'In Progress', 'Resolved', 'Closed'],
                datasets: [{
                    label: 'Number of Bugs',
                    data: [8, 4, 23, 15],
                    backgroundColor: [
                        '#dc3545', // Danger - Open
                        '#ffc107', // Warning - In Progress
                        '#198754', // Success - Resolved
                        '#6c757d'  // Secondary - Closed
                    ],
                    borderWidth: 1,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                return `${context[0].label} Bugs`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    },
    
    async loadRecentActivity() {
        try {
            const activity = await this.fetchRecentActivity();
            this.updateActivityFeed(activity);
        } catch (error) {
            console.error('Failed to load recent activity:', error);
            this.showActivityError();
        }
    },
    
    async fetchRecentActivity() {
        // Simulate API call - would be replaced with actual PHP endpoint
        return [
            {
                id: 1,
                user: 'John Doe',
                action: 'created',
                entity: 'requirement',
                description: 'User Authentication System',
                timestamp: new Date(Date.now() - 1000 * 60 * 15).toISOString(), // 15 minutes ago
                icon: 'fas fa-plus',
                color: 'text-success'
            },
            {
                id: 2,
                user: 'Jane Smith',
                action: 'updated',
                entity: 'test case',
                description: 'Login validation test',
                timestamp: new Date(Date.now() - 1000 * 60 * 30).toISOString(), // 30 minutes ago
                icon: 'fas fa-edit',
                color: 'text-primary'
            },
            {
                id: 3,
                user: 'Bob Johnson',
                action: 'reported',
                entity: 'bug',
                description: 'Password reset not working',
                timestamp: new Date(Date.now() - 1000 * 60 * 60).toISOString(), // 1 hour ago
                icon: 'fas fa-bug',
                color: 'text-danger'
            },
            {
                id: 4,
                user: 'Alice Brown',
                action: 'completed',
                entity: 'test run',
                description: 'Regression Testing - Sprint 5',
                timestamp: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(), // 2 hours ago
                icon: 'fas fa-check',
                color: 'text-success'
            }
        ];
    },
    
    updateActivityFeed(activities) {
        const container = document.getElementById('recent-activity');
        if (!container) return;
        
        if (activities.length === 0) {
            container.innerHTML = '<p class="text-muted">No recent activity found.</p>';
            return;
        }
        
        const html = activities.map(activity => `
            <div class="activity-item border-start-primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <i class="${activity.icon} ${activity.color} me-2"></i>
                            <strong>${activity.user}</strong>
                            <span class="text-muted ms-1">${activity.action}</span>
                            <span class="text-muted ms-1">${activity.entity}</span>
                        </div>
                        <div class="text-truncate" title="${activity.description}">
                            ${activity.description}
                        </div>
                    </div>
                    <small class="activity-time text-muted">
                        ${this.timeAgo(activity.timestamp)}
                    </small>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    },
    
    showActivityError() {
        const container = document.getElementById('recent-activity');
        if (!container) return;
        
        container.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-exclamation-triangle"></i>
                <p class="mt-2">Unable to load recent activity</p>
                <button class="btn btn-sm btn-outline-primary" onclick="Dashboard.loadRecentActivity()">
                    <i class="fas fa-refresh"></i> Retry
                </button>
            </div>
        `;
    },
    
    timeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diffInSeconds = Math.floor((now - time) / 1000);
        
        if (diffInSeconds < 60) {
            return 'Just now';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
    },
    
    setupRefreshTimer() {
        // Refresh dashboard data every 5 minutes
        setInterval(() => {
            this.loadStats();
            this.loadRecentActivity();
        }, 5 * 60 * 1000);
    },
    
    exportData(format) {
        const data = {
            stats: this.getStatsData(),
            charts: this.getChartsData(),
            timestamp: new Date().toISOString()
        };
        
        if (format === 'json') {
            this.downloadJSON(data, 'dashboard-export.json');
        } else if (format === 'csv') {
            this.downloadCSV(data, 'dashboard-export.csv');
        }
    },
    
    getStatsData() {
        return {
            requirements: document.getElementById('total-requirements')?.textContent || '0',
            testcases: document.getElementById('total-testcases')?.textContent || '0',
            bugs: document.getElementById('total-bugs')?.textContent || '0'
        };
    },
    
    getChartsData() {
        const data = {};
        
        if (this.charts.testExecution) {
            data.testExecution = this.charts.testExecution.data;
        }
        
        if (this.charts.bugStatus) {
            data.bugStatus = this.charts.bugStatus.data;
        }
        
        return data;
    },
    
    downloadJSON(data, filename) {
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        this.downloadBlob(blob, filename);
    },
    
    downloadCSV(data, filename) {
        // Convert data to CSV format
        let csv = 'Metric,Value\n';
        Object.entries(data.stats).forEach(([key, value]) => {
            csv += `${key},${value}\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        this.downloadBlob(blob, filename);
    },
    
    downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
};

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    Dashboard.init();
});

// Export for global access
window.Dashboard = Dashboard;

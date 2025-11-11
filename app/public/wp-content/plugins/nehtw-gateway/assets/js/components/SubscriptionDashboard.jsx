import React, { useState, useEffect } from 'react';

const SubscriptionDashboard = () => {
    const [subscription, setSubscription] = useState(null);
    const [usage, setUsage] = useState(null);
    const [invoices, setInvoices] = useState([]);
    const [plans, setPlans] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showPlanModal, setShowPlanModal] = useState(false);

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        try {
            const [subRes, invoiceRes, planRes] = await Promise.all([
                fetch('/wp-json/nehtw/v1/subscription', {
                    credentials: 'same-origin',
                }),
                fetch('/wp-json/nehtw/v1/invoices', {
                    credentials: 'same-origin',
                }),
                fetch('/wp-json/nehtw/v1/plans', {
                    credentials: 'same-origin',
                }),
            ]);

            const subData = await subRes.json();
            const invoiceData = await invoiceRes.json();
            const planData = await planRes.json();

            setSubscription(subData.subscription);
            setUsage(subData.usage);
            setInvoices(invoiceData);
            setPlans(planData);
            setLoading(false);
        } catch (error) {
            console.error('Error loading data:', error);
            setLoading(false);
        }
    };

    const handlePlanChange = async (newPlanKey) => {
        try {
            const response = await fetch('/wp-json/nehtw/v1/subscription/change-plan', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    new_plan_key: newPlanKey,
                    apply_immediately: true,
                }),
            });

            const result = await response.json();

            if (result.success) {
                alert('Plan changed successfully!');
                loadData();
                setShowPlanModal(false);
            } else {
                alert('Failed to change plan: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error changing plan: ' + error.message);
        }
    };

    const handlePause = async () => {
        if (!confirm('Are you sure you want to pause your subscription?')) {
            return;
        }

        try {
            const response = await fetch('/wp-json/nehtw/v1/subscription/pause', {
                method: 'POST',
                credentials: 'same-origin',
            });

            const result = await response.json();

            if (result.success) {
                alert('Subscription paused successfully!');
                loadData();
            }
        } catch (error) {
            alert('Error pausing subscription: ' + error.message);
        }
    };

    const handleCancel = async () => {
        const reason = prompt('Please tell us why you\'re cancelling (optional):');

        if (reason === null) return; // User clicked cancel

        if (!confirm('Are you sure you want to cancel your subscription?')) {
            return;
        }

        try {
            const response = await fetch('/wp-json/nehtw/v1/subscription/cancel', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    reason: reason,
                    cancel_immediately: false,
                }),
            });

            const result = await response.json();

            if (result.success) {
                alert('Subscription cancelled. You will retain access until the end of your billing period.');
                loadData();
            }
        } catch (error) {
            alert('Error cancelling subscription: ' + error.message);
        }
    };

    if (loading) {
        return (
            <div className="nehtw-subscription-dashboard loading">
                <div className="loading-spinner"></div>
                <p>Loading your subscription details...</p>
            </div>
        );
    }

    if (!subscription) {
        return (
            <div className="nehtw-subscription-dashboard no-subscription">
                <h2>No Active Subscription</h2>
                <p>You don't have an active subscription. Choose a plan to get started!</p>
                <button className="nehtw-btn-primary" onClick={() => setShowPlanModal(true)}>
                    View Plans
                </button>
            </div>
        );
    }

    const usagePercentage = usage ? (usage.total_usage / subscription.points_per_interval) * 100 : 0;

    return (
        <div className="nehtw-subscription-dashboard">
            {/* Current Plan Card */}
            <div className="nehtw-glass-card current-plan-card">
                <div className="card-header">
                    <h2>Current Plan</h2>
                    <span className="nehtw-badge plan-badge">
                        {plans[subscription.plan_key]?.name || subscription.plan_key}
                    </span>
                </div>
                <div className="plan-details">
                    <div className="points-balance">
                        <div className="balance-amount">{subscription.points_per_interval}</div>
                        <div className="balance-label">Points per {subscription.interval}</div>
                    </div>
                    <div className="renewal-info">
                        <div className="renewal-label">Next Billing</div>
                        <div className="renewal-date">
                            {new Date(subscription.next_renewal_at).toLocaleDateString()}
                        </div>
                        <div className="renewal-amount">
                            ${plans[subscription.plan_key]?.price || 0}/{subscription.interval}
                        </div>
                    </div>
                </div>
                <div className="plan-actions">
                    <button className="nehtw-btn-primary" onClick={() => setShowPlanModal(true)}>
                        Change Plan
                    </button>
                    <button className="nehtw-btn-secondary" onClick={handlePause}>
                        Pause Subscription
                    </button>
                </div>
            </div>

            {/* Usage Chart */}
            {usage && (
                <div className="nehtw-glass-card usage-card">
                    <h3>Usage This Month</h3>
                    <div className="usage-bar-container">
                        <div className="usage-bar">
                            <div 
                                className="usage-fill"
                                style={{ width: `${Math.min(usagePercentage, 100)}%` }}
                            ></div>
                        </div>
                        <div className="usage-label">
                            {usage.total_usage} of {subscription.points_per_interval} points used
                            ({usagePercentage.toFixed(0)}%)
                        </div>
                    </div>
                </div>
            )}

            {/* Billing History */}
            <div className="nehtw-glass-card billing-history-card">
                <div className="card-header">
                    <h3>Billing History</h3>
                </div>
                <table className="nehtw-billing-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.map(invoice => (
                            <tr key={invoice.id}>
                                <td>{new Date(invoice.created_at).toLocaleDateString()}</td>
                                <td>{invoice.invoice_number}</td>
                                <td>${parseFloat(invoice.total_amount).toFixed(2)}</td>
                                <td>
                                    <span className={`nehtw-status-badge nehtw-status-${invoice.status}`}>
                                        {invoice.status}
                                    </span>
                                </td>
                                <td>
                                    <a 
                                        href={`/wp-json/nehtw/v1/invoices/${invoice.id}/download`}
                                        className="nehtw-btn-link"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Download PDF
                                    </a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Subscription Actions */}
            <div className="nehtw-glass-card actions-card">
                <h3>Subscription Actions</h3>
                <div className="action-buttons">
                    <button className="nehtw-btn-secondary" onClick={handleCancel}>
                        Cancel Subscription
                    </button>
                </div>
            </div>

            {/* Plan Change Modal */}
            {showPlanModal && (
                <PlanModal
                    plans={plans}
                    currentPlan={subscription.plan_key}
                    onClose={() => setShowPlanModal(false)}
                    onSelect={handlePlanChange}
                />
            )}
        </div>
    );
};

const PlanModal = ({ plans, currentPlan, onClose, onSelect }) => {
    return (
        <div className="nehtw-modal-overlay" onClick={onClose}>
            <div className="nehtw-modal nehtw-glass-card" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Choose Your Plan</h2>
                    <button className="modal-close" onClick={onClose}>×</button>
                </div>
                <div className="modal-body">
                    <div className="plans-grid">
                        {Object.entries(plans).map(([key, plan]) => (
                            <div 
                                key={key} 
                                className={`plan-card ${key === currentPlan ? 'current' : ''}`}
                            >
                                <h3>{plan.name}</h3>
                                <div className="plan-price">${plan.price}/month</div>
                                <div className="plan-points">{plan.points} points</div>
                                <ul className="plan-features">
                                    {plan.features && plan.features.map((feature, i) => (
                                        <li key={i}>{feature}</li>
                                    ))}
                                </ul>
                                {key === currentPlan ? (
                                    <button className="nehtw-btn-secondary" disabled>
                                        Current Plan
                                    </button>
                                ) : (
                                    <button 
                                        className="nehtw-btn-primary"
                                        onClick={() => onSelect(key)}
                                    >
                                        Select Plan
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default SubscriptionDashboard;


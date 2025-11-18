(function () {
    const settings = window.ArtlyAiSettings || null;
    const baseUrl = settings && settings.restUrl ? settings.restUrl.replace(/\/$/, '') : '';
  
    const ArtlyAI = {
      elements: {},
      pollInterval: null,
      pollTimeout: null,
      currentJobId: null,
      softLimit: 300,
  
      boot() {
        document.addEventListener('DOMContentLoaded', () => {
          this.cacheElements();
  
          if (!settings) {
            return;
          }
  
          if (this.elements.prompt) {
            this.initGeneratorPage();
          }
  
          if (this.elements.historyList) {
            this.initHistoryPage();
          }
        });
      },
  
      cacheElements() {
        this.elements.prompt = document.getElementById('artly-ai-prompt');
        this.elements.counter = document.getElementById('artly-ai-counter');
        this.elements.generateBtn = document.getElementById('artly-ai-generate-btn');
        this.elements.progressFill = document.getElementById('artly-ai-progress-fill');
        this.elements.resultGrid = document.getElementById('artly-ai-result-grid');
        this.elements.stateContainer = document.getElementById('artly-ai-state');
        this.elements.nopointsDynamic = document.getElementById('artly-ai-nopoints-dynamic');
        this.elements.errorMessage = document.getElementById('artly-ai-error-message');
        this.elements.errorRetry = document.getElementById('artly-ai-error-retry');
        this.elements.balanceAmount = document.getElementById('artly-ai-balance-amount');
        this.elements.historyList = document.getElementById('artly-ai-history-list');
        this.elements.pageInfo = document.getElementById('artly-ai-page-info');
        this.elements.pagePrev = document.querySelector('.artly-ai-page-prev');
        this.elements.pageNext = document.querySelector('.artly-ai-page-next');
        this.elements.filterButtons = Array.from(document.querySelectorAll('.artly-ai-filter'));
      },
  
      initGeneratorPage() {
        this.elements.prompt.addEventListener('input', () => this.updateCounter());
        this.updateCounter();
  
        document.querySelectorAll('.artly-ai-chip[data-preset]').forEach((chip) => {
          chip.addEventListener('click', () => this.applyPreset(chip.dataset.preset));
        });
  
        if (this.elements.generateBtn) {
          this.elements.generateBtn.addEventListener('click', () => this.startGeneration());
        }
  
        if (this.elements.errorRetry) {
          this.elements.errorRetry.addEventListener('click', () => this.resetToPrompt());
        }
  
        const params = new URLSearchParams(window.location.search);
        const jobId = params.get('job_id');
        if (jobId) {
          this.loadJob(jobId);
        }
      },
  
      initHistoryPage() {
        this.fetchHistory(1);
  
        this.elements.filterButtons.forEach((btn) => {
          btn.addEventListener('click', () => {
            this.elements.filterButtons.forEach((b) => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            this.applyFilter(btn.dataset.filter);
          });
        });
  
        if (this.elements.pagePrev) {
          this.elements.pagePrev.addEventListener('click', () => this.changePage('prev'));
        }
  
        if (this.elements.pageNext) {
          this.elements.pageNext.addEventListener('click', () => this.changePage('next'));
        }
      },
  
      updateCounter() {
        if (!this.elements.prompt || !this.elements.counter) return;
        const value = this.elements.prompt.value || '';
        const length = value.length;
        this.elements.counter.textContent = `${length} / ${this.softLimit}`;
        this.elements.counter.classList.toggle('is-over-limit', length > this.softLimit);
      },
  
      applyPreset(text) {
        if (!this.elements.prompt) return;
        const current = this.elements.prompt.value.trim();
        const combined = current ? `${current} ${text}` : text;
        this.elements.prompt.value = combined;
        this.updateCounter();
        this.elements.prompt.focus();
      },
  
      async startGeneration() {
        if (!settings) return;
        if (!this.elements.prompt || !this.elements.generateBtn) return;
  
        const prompt = this.elements.prompt.value.trim();
        if (!prompt) {
          this.showError(settings.texts?.promptRequired || 'Please enter a prompt to continue.');
          return;
        }
  
        if (settings.pointsBalance !== undefined && settings.pointsBalance < (settings.costs?.generate || 0)) {
          this.showNoPoints(settings.costs?.generate || 0, settings.pointsBalance);
          return;
        }
  
        this.toggleControls(true);
        this.showState('processing');
  
        try {
          const response = await this.request('/ai/create', {
            method: 'POST',
            body: JSON.stringify({ prompt }),
          });
  
          console.log('AI CREATE RESPONSE:', response);
  
          if (!response || response.success === false) {
            if (response && response.code === 'insufficient_points') {
              this.showNoPoints(
                response.cost_points || (settings.costs?.generate || 0),
                response.user_balance || settings.pointsBalance || 0
              );
            } else {
              this.showError(
                (response && (response.error || response.message)) ||
                  settings.texts?.genericError ||
                  'Unable to start generation.'
              );
            }
            this.toggleControls(false);
            return;
          }
  
          // 🔧 IMPORTANT: be defensive when reading job_id
          this.currentJobId =
            response.job_id ||
            (response.data && response.data.job_id) ||
            response.nehtw_job_id ||
            null;
  
          console.log('Stored job_id from create:', this.currentJobId);
  
          if (!this.currentJobId) {
            this.showError('AI job started but job_id is missing in the server response.');
            this.toggleControls(false);
            return;
          }
  
          this.updateBalance(response.user_balance);
          this.pollJobStatus(this.currentJobId);
        } catch (error) {
          this.showError(error.message || settings.texts?.genericError || 'Something went wrong.');
          this.toggleControls(false);
        }
      },
  
      pollJobStatus(jobId) {
        // Guard: never poll with an invalid job id
        if (!jobId) {
          console.error('pollJobStatus called without a valid jobId');
          this.showError('Cannot check status: missing job id.');
          this.toggleControls(false);
          return;
        }
  
        if (this.pollInterval) {
          clearInterval(this.pollInterval);
        }
        if (this.pollTimeout) {
          clearTimeout(this.pollTimeout);
        }
  
        this.showState('processing');
        this.setProgress(10);
  
        const startTime = Date.now();
        const maxPollTime = 5 * 60 * 1000; // 5 minutes max
        let pollCount = 0;
        const maxPolls = 150; // Max 150 polls (5 minutes at 2s intervals)
  
        const poll = async () => {
          try {
            pollCount++;
            const elapsed = Date.now() - startTime;
            
            // Stop polling if we've exceeded max time or max polls
            if (elapsed > maxPollTime || pollCount > maxPolls) {
              clearInterval(this.pollInterval);
              this.pollInterval = null;
              if (this.pollTimeout) {
                clearTimeout(this.pollTimeout);
                this.pollTimeout = null;
              }
              this.showError(
                settings.texts?.timeoutError ||
                'Generation is taking longer than expected. Please try again or contact support if this persists.'
              );
              this.toggleControls(false);
              return;
            }
  
            console.log(`Polling AI status for job_id: ${jobId} (attempt ${pollCount}, ${Math.round(elapsed/1000)}s elapsed)`);
            const response = await this.request(`/ai/status?job_id=${encodeURIComponent(jobId)}`);
  
            console.log('AI STATUS RESPONSE:', response);
            console.log('Status value:', response?.status, 'Type:', typeof response?.status);
            
            // Log debug info if available (when WP_DEBUG is enabled)
            if (response?._debug) {
              console.log('DEBUG - Raw Nehtw Response:', response._debug);
              console.log('DEBUG - Raw Status:', response._debug.raw_status);
              console.log('DEBUG - Full Raw Body:', response._debug.raw_body);
            }
  
            if (!response || response.success === false) {
              clearInterval(this.pollInterval);
              this.pollInterval = null;
              if (this.pollTimeout) {
                clearTimeout(this.pollTimeout);
                this.pollTimeout = null;
              }
              this.showError(
                (response && (response.error || response.message)) ||
                  settings.texts?.genericError ||
                  'Unable to fetch job status.'
              );
              this.toggleControls(false);
              return;
            }
  
            // Some backends might not echo job_id again; keep the existing one as fallback
            this.currentJobId =
              response.job_id ||
              (response.data && response.data.job_id) ||
              this.currentJobId;
  
            const percentage = typeof response.percentage === 'number' ? response.percentage : 0;
            this.setProgress(percentage > 0 ? percentage : 25);
  
            const status = response.status ? String(response.status).toLowerCase() : 'pending';
            const files = response.files || [];
            const hasFiles = Array.isArray(files) && files.length > 0;
            
            // Check if we have files even if status isn't "completed" yet
            // Some APIs return files before marking status as completed
            if (hasFiles && (status === 'completed' || status === 'done' || status === 'finished' || status === 'processing')) {
              // If we have files and status indicates completion OR we have files with high percentage
              const percentage = typeof response.percentage === 'number' ? response.percentage : 0;
              if (status === 'completed' || status === 'done' || status === 'finished' || percentage >= 90) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
                if (this.pollTimeout) {
                  clearTimeout(this.pollTimeout);
                  this.pollTimeout = null;
                }
                this.renderResults(files);
                this.showState('result');
                this.toggleControls(false);
                return;
              }
            }
            
            if (status === 'completed' || status === 'done' || status === 'finished') {
              clearInterval(this.pollInterval);
              this.pollInterval = null;
              if (this.pollTimeout) {
                clearTimeout(this.pollTimeout);
                this.pollTimeout = null;
              }
              this.renderResults(files);
              this.showState('result');
              this.toggleControls(false);
            } else if (status === 'failed' || status === 'error' || status === 'cancelled') {
              clearInterval(this.pollInterval);
              this.pollInterval = null;
              if (this.pollTimeout) {
                clearTimeout(this.pollTimeout);
                this.pollTimeout = null;
              }
              this.showError(
                response.error ||
                settings.texts?.generationFailed ||
                'Image generation failed. Please try again.'
              );
              this.toggleControls(false);
            } else {
              // Still processing, continue polling
              this.showState('processing');
            }
          } catch (error) {
            console.error('Poll error:', error);
            clearInterval(this.pollInterval);
            this.pollInterval = null;
            if (this.pollTimeout) {
              clearTimeout(this.pollTimeout);
              this.pollTimeout = null;
            }
            this.showError(error.message || settings.texts?.genericError || 'Unable to fetch job status.');
            this.toggleControls(false);
          }
        };
  
        poll();
        this.pollInterval = setInterval(poll, 2000);
        
        // Set a hard timeout as backup
        this.pollTimeout = setTimeout(() => {
          if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
            this.showError(
              settings.texts?.timeoutError ||
              'Generation timeout. Please try again.'
            );
            this.toggleControls(false);
          }
        }, maxPollTime);
      },
  
      renderResults(files) {
        if (!this.elements.resultGrid) return;
        this.elements.resultGrid.innerHTML = '';
  
        if (!files || !files.length) {
          this.elements.resultGrid.innerHTML = `<p class="artly-ai-help">${
            settings.texts?.noResults || 'No images returned yet.'
          }</p>`;
          return;
        }
  
        files.forEach((file) => {
          const card = document.createElement('div');
          card.className = 'artly-ai-image-card';
  
          const img = document.createElement('img');
          img.src = file.thumb_lg || file.thumb_sm || '';
          img.alt = settings.texts?.resultAlt || 'Generated image';
          card.appendChild(img);
  
          const actions = document.createElement('div');
          actions.className = 'artly-ai-image-actions';
  
          if (file.download_url) {
            const download = document.createElement('a');
            download.href = file.download_url;
            download.target = '_blank';
            download.rel = 'noopener';
            download.className = 'artly-btn artly-btn-sm artly-btn-primary';
            download.textContent = settings.texts?.downloadLabel || 'Download';
            actions.appendChild(download);
          }
  
          const varyBtn = document.createElement('button');
          varyBtn.type = 'button';
          varyBtn.className = 'artly-btn artly-btn-sm artly-btn-ghost';
          varyBtn.textContent = settings.texts?.varyLabel || 'Vary';
          varyBtn.dataset.jobId = this.currentJobId || '';
          varyBtn.dataset.index = file.index || 0;
          varyBtn.addEventListener('click', () => this.startAction(this.currentJobId, 'vary', file.index));
          actions.appendChild(varyBtn);
  
          const upscaleBtn = document.createElement('button');
          upscaleBtn.type = 'button';
          upscaleBtn.className = 'artly-btn artly-btn-sm artly-btn-ghost';
          upscaleBtn.textContent = settings.texts?.upscaleLabel || 'Upscale';
          upscaleBtn.dataset.jobId = this.currentJobId || '';
          upscaleBtn.dataset.index = file.index || 0;
          upscaleBtn.addEventListener('click', () => this.startAction(this.currentJobId, 'upscale', file.index));
          actions.appendChild(upscaleBtn);
  
          card.appendChild(actions);
          this.elements.resultGrid.appendChild(card);
        });
      },
  
      async startAction(jobId, action, index) {
        if (!jobId || !action) return;
  
        const cost = action === 'upscale' ? (settings.costs?.upscale || 0) : (settings.costs?.vary || 0);
        if (settings.pointsBalance !== undefined && settings.pointsBalance < cost) {
          this.showNoPoints(cost, settings.pointsBalance);
          return;
        }
  
        const proceed = window.confirm(
          settings.texts?.actionConfirm || `This will use ${cost} points. Continue?`
        );
        if (!proceed) return;
  
        this.toggleControls(true);
        this.showState('processing');
  
        try {
          const response = await this.request('/ai/action', {
            method: 'POST',
            body: JSON.stringify({ job_id: jobId, action, index, vary_type: 'subtle' }),
          });
  
          console.log('AI ACTION RESPONSE:', response);
  
          if (!response || response.success === false) {
            if (response && response.code === 'insufficient_points') {
              this.showNoPoints(
                response.cost_points || cost,
                response.user_balance || settings.pointsBalance || 0
              );
            } else {
              this.showError(
                (response && (response.error || response.message)) ||
                  settings.texts?.genericError ||
                  'Unable to start action.'
              );
            }
            this.toggleControls(false);
            return;
          }
  
          this.currentJobId =
            response.job_id ||
            (response.data && response.data.job_id) ||
            response.nehtw_job_id ||
            null;
  
          console.log('Stored job_id from action:', this.currentJobId);
  
          if (!this.currentJobId) {
            this.showError('Action started but job_id is missing in the server response.');
            this.toggleControls(false);
            return;
          }
  
          this.updateBalance(response.user_balance);
          this.pollJobStatus(this.currentJobId);
        } catch (error) {
          this.showError(error.message || settings.texts?.genericError || 'Unable to start action.');
          this.toggleControls(false);
        }
      },
  
      async loadJob(jobId) {
        this.toggleControls(true);
        this.showState('processing');
  
        try {
          const response = await this.request(`/ai/status?job_id=${encodeURIComponent(jobId)}`);
  
          console.log('AI LOAD JOB STATUS RESPONSE:', response);
  
          if (!response || response.success === false) {
            this.showError(
              (response && (response.error || response.message)) ||
                settings.texts?.genericError ||
                'Unable to load job.'
            );
            this.toggleControls(false);
            return;
          }
  
          this.currentJobId =
            response.job_id ||
            (response.data && response.data.job_id) ||
            jobId;
  
          if (response.status === 'completed') {
            this.renderResults(response.files || []);
            this.showState('result');
            this.toggleControls(false);
          } else {
            this.pollJobStatus(this.currentJobId);
          }
        } catch (error) {
          this.showError(error.message || settings.texts?.genericError || 'Unable to load job.');
          this.toggleControls(false);
        }
      },
  
      showState(state) {
        const states = {
          empty: document.getElementById('artly-ai-empty-state'),
          processing: document.getElementById('artly-ai-processing-state'),
          result: document.getElementById('artly-ai-result-state'),
          nopoints: document.getElementById('artly-ai-nopoints-state'),
          error: document.getElementById('artly-ai-error-state'),
        };
  
        Object.keys(states).forEach((key) => {
          if (states[key]) {
            if (key === state) {
              states[key].removeAttribute('hidden');
            } else {
              states[key].setAttribute('hidden', 'hidden');
            }
          }
        });
      },
  
      resetToPrompt() {
        this.showState('empty');
        this.toggleControls(false);
      },
  
      showNoPoints(required, balance) {
        if (this.elements.nopointsDynamic) {
          this.elements.nopointsDynamic.textContent = `${
            settings.texts?.insufficientPoints || 'You do not have enough points for this action.'
          } (${balance} / ${required})`;
        }
        this.showState('nopoints');
      },
  
      showError(message) {
        if (this.elements.errorMessage) {
          this.elements.errorMessage.textContent =
            message || settings.texts?.genericError || 'Something went wrong.';
        }
        this.showState('error');
      },
  
      setProgress(value) {
        if (!this.elements.progressFill) return;
        const percent = Math.max(5, Math.min(100, value));
        this.elements.progressFill.style.width = `${percent}%`;
        this.elements.progressFill.parentElement?.setAttribute('aria-valuenow', `${percent}`);
      },
  
      toggleControls(disabled) {
        if (this.elements.generateBtn) {
          this.elements.generateBtn.disabled = disabled;
        }
  
        document.querySelectorAll('.artly-ai-chip, #artly-ai-style, #artly-ai-prompt').forEach((el) => {
          if (disabled) {
            el.setAttribute('disabled', 'disabled');
          } else {
            el.removeAttribute('disabled');
          }
        });
      },
  
      updateBalance(amount) {
        if (typeof amount === 'number' && !isNaN(amount)) {
          settings.pointsBalance = amount;
          if (this.elements.balanceAmount) {
            this.elements.balanceAmount.textContent = Math.max(0, Math.floor(amount)).toLocaleString();
          }
        }
      },
  
      async fetchHistory(page) {
        const perPage = 20;
        const targetPage = page || 1;
  
        try {
          const response = await this.request(
            `/ai/history?page=${encodeURIComponent(targetPage)}&per_page=${perPage}`
          );
  
          if (!response || response.success === false) {
            this.showHistoryEmpty(settings.texts?.genericError || 'Unable to fetch history.');
            return;
          }
  
          const jobs = Array.isArray(response.jobs) ? response.jobs : [];
          this.renderHistory(jobs);
          this.updatePagination(response.pagination || {}, targetPage);
        } catch (error) {
          this.showHistoryEmpty(error.message || settings.texts?.genericError || 'Unable to fetch history.');
        }
      },
  
      renderHistory(jobs) {
        if (!this.elements.historyList) return;
        this.elements.historyList.innerHTML = '';
  
        if (!jobs.length) {
          this.showHistoryEmpty(
            settings.texts?.emptyHistory || 'No AI jobs yet. Start generating to see your history here.'
          );
          return;
        }
  
        jobs.forEach((job) => {
          const card = document.createElement('div');
          card.className = 'artly-ai-history-card';
          card.dataset.type = job.type || 'imagine';
  
          if (job.preview_thumb) {
            const img = document.createElement('img');
            img.src = job.preview_thumb;
            img.alt = job.prompt ? job.prompt.slice(0, 80) : 'AI preview';
            img.className = 'artly-ai-history-thumb';
            card.appendChild(img);
          }
  
          const metaRow = document.createElement('div');
          metaRow.className = 'artly-ai-history-meta';
  
          const badge = document.createElement('span');
          badge.className = 'artly-ai-badge';
          badge.textContent = this.getTypeLabel(job.type);
          metaRow.appendChild(badge);
  
          const status = document.createElement('span');
          status.className = 'artly-ai-status';
          status.textContent = this.getStatusLabel(job.status);
          metaRow.appendChild(status);
  
          card.appendChild(metaRow);
  
          const title = document.createElement('p');
          title.className = 'artly-ai-history-title';
          title.textContent =
            job.prompt || settings.texts?.untitledPrompt || 'Untitled prompt';
          card.appendChild(title);
  
          if (job.created_at) {
            const date = document.createElement('div');
            date.className = 'artly-ai-history-date';
            date.textContent = this.formatDate(job.created_at);
            card.appendChild(date);
          }
  
          const actions = document.createElement('div');
          actions.className = 'artly-ai-history-actions';
  
          const open = document.createElement('a');
          open.className = 'artly-btn artly-btn-sm artly-btn-primary';
          const base = settings.urls?.aiGenerator || '/ai-generator/';
          open.href = `${base}?job_id=${encodeURIComponent(job.job_id || '')}`;
          open.textContent = settings.texts?.openInGenerator || 'Open in Generator';
          actions.appendChild(open);
  
          card.appendChild(actions);
          this.elements.historyList.appendChild(card);
        });
      },
  
      showHistoryEmpty(message) {
        if (!this.elements.historyList) return;
        this.elements.historyList.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'artly-ai-history-card';
        empty.dataset.type = 'all';
        empty.textContent = message;
        this.elements.historyList.appendChild(empty);
      },
  
      updatePagination(pagination, currentPage) {
        const totalPages = pagination.total_pages || pagination.total || 1;
        const current = pagination.current_page || currentPage || 1;
  
        if (this.elements.pageInfo) {
          this.elements.pageInfo.textContent = `${
            settings.texts?.pageLabel || 'Page'
          } ${current} / ${totalPages}`;
        }
  
        if (this.elements.pagePrev) {
          this.elements.pagePrev.disabled = current <= 1;
          this.elements.pagePrev.dataset.page = current;
        }
  
        if (this.elements.pageNext) {
          this.elements.pageNext.disabled = current >= totalPages;
          this.elements.pageNext.dataset.page = current;
        }
      },
  
      changePage(direction) {
        const current = parseInt(this.elements.pagePrev?.dataset.page || '1', 10) || 1;
        const nextPage = direction === 'next' ? current + 1 : current - 1;
        if (nextPage < 1) return;
        this.fetchHistory(nextPage);
      },
  
      applyFilter(type) {
        if (!this.elements.historyList) return;
        const cards = Array.from(
          this.elements.historyList.querySelectorAll('.artly-ai-history-card')
        );
        cards.forEach((card) => {
          if (type === 'all') {
            card.hidden = false;
            return;
          }
          card.hidden = card.dataset.type !== type;
        });
      },
  
      formatDate(dateString) {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        return date.toLocaleString(undefined, {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
        });
      },
  
      getTypeLabel(type) {
        const map = {
          vary: settings.texts?.typeVary || 'Variation',
          upscale: settings.texts?.typeUpscale || 'Upscale',
          imagine: settings.texts?.typeImagine || 'Generate',
        };
        return map[type] || map.imagine;
      },
  
      getStatusLabel(status) {
        const normalized = (status || '').toString().toLowerCase();
        if (['processing', 'pending'].includes(normalized)) {
          return settings.texts?.statusProcessing || 'Processing';
        }
        if (normalized === 'completed') {
          return settings.texts?.statusCompleted || 'Completed';
        }
        return settings.texts?.statusPending || 'Pending';
      },
  
      async request(path, options = {}) {
        const url = `${baseUrl}${path}`;
        const response = await fetch(url, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': settings?.nonce || '',
          },
          ...options,
        });
  
        const json = await response.json();
        return json;
      },
    };
  
    ArtlyAI.boot();
  })();
  
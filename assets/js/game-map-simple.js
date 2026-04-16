class MapAdventureGame extends AdventureGame {
  constructor(options = {}) {
    const mapTheme = options.mapTheme || 'mountain';
    const modeMap = {
      island: 'maze',
      mountain: 'mountain',
      lake: 'ship',
      forest: 'maze'
    };
    super({
      ...options,
      gameType: modeMap[mapTheme] || 'mountain'
    });
    this.mapTheme = mapTheme;
  }

  render() {
    super.render();
    if (!this.container) return;
    const wrapper = this.container.querySelector('.enhanced-game');
    if (!wrapper) return;
    wrapper.classList.add(`map-theme-${this.mapTheme}`);
    const header = wrapper.querySelector('.enhanced-game-header');
    if (header) {
      const emojis = {
        island: '🏝️',
        mountain: '⛰️',
        lake: '⛵',
        forest: '🌲'
      };
      const label = {
        island: 'مغامرة الجزيرة',
        mountain: 'مغامرة الجبل',
        lake: 'مغامرة البحر',
        forest: 'مغامرة الغابة'
      };
      const badge = document.createElement('div');
      badge.className = 'map-mode-badge';
      badge.textContent = `${emojis[this.mapTheme] || '🎮'} ${label[this.mapTheme] || 'مغامرة تعليمية'}`;
      header.prepend(badge);
    }
  }
}

window.MapAdventureGame = MapAdventureGame;

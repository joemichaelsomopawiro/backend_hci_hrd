<template>
  <div v-if="show" class="popup-overlay" @click="handleOverlayClick">
    <div class="popup-content" @click.stop>
      <div class="popup-icon">{{ icon }}</div>
      <div class="popup-title">{{ title }}</div>
      <div class="popup-message" v-html="message"></div>
      <div class="popup-buttons">
        <button
          v-for="(button, index) in buttons"
          :key="index"
          :class="['popup-btn', button.class || 'primary']"
          @click="handleButtonClick(button)"
        >
          {{ button.text }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CustomPopup',
  props: {
    show: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: 'Informasi'
    },
    message: {
      type: String,
      default: 'Pesan popup'
    },
    icon: {
      type: String,
      default: 'ℹ️'
    },
    buttons: {
      type: Array,
      default: () => [{ text: 'OK', class: 'success', action: 'close' }]
    }
  },
  
  emits: ['close'],
  
  methods: {
    handleOverlayClick() {
      this.$emit('close')
    },
    
    handleButtonClick(button) {
      if (button.action === 'close') {
        this.$emit('close')
      } else if (button.action === 'confirm' && button.handler) {
        button.handler()
        this.$emit('close')
      } else if (button.handler) {
        button.handler()
      }
    }
  }
}
</script>

<style scoped>
.popup-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  animation: fadeIn 0.3s ease;
}

.popup-content {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: white;
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  max-width: 500px;
  width: 90%;
  text-align: center;
  animation: popIn 0.3s ease;
}

.popup-icon {
  font-size: 60px;
  margin-bottom: 20px;
}

.popup-title {
  font-size: 24px;
  font-weight: bold;
  margin-bottom: 15px;
  color: #333;
}

.popup-message {
  font-size: 16px;
  line-height: 1.5;
  color: #666;
  margin-bottom: 25px;
}

.popup-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
}

.popup-btn {
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  min-width: 100px;
}

.popup-btn.primary {
  background: #007bff;
  color: white;
}

.popup-btn.primary:hover {
  background: #0056b3;
}

.popup-btn.secondary {
  background: #6c757d;
  color: white;
}

.popup-btn.secondary:hover {
  background: #545b62;
}

.popup-btn.success {
  background: #28a745;
  color: white;
}

.popup-btn.success:hover {
  background: #1e7e34;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes popIn {
  from { 
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.8);
  }
  to { 
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }
}

@media (max-width: 768px) {
  .popup-content {
    width: 95%;
    padding: 20px;
  }
  
  .popup-buttons {
    flex-direction: column;
  }
  
  .popup-btn {
    width: 100%;
  }
}
</style> 
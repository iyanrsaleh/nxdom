import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Animated,
  TouchableOpacity,
  Dimensions,
  Platform,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import FeatherIcon from 'react-native-vector-icons/Feather';
import Colors from '../utils/Color';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

// Toast Manager untuk global toast
let toastManager = null;

// Toast Component
const Toast = ({
  visible = false,
  message = '',
  type = 'info', // 'success', 'error', 'warning', 'info'
  position = 'top', // 'top', 'bottom'
  duration = 3000, // milliseconds, 0 = no auto dismiss
  onClose,
  icon,
  action,
  actionLabel,
  style,
  textStyle,
}) => {
  const [isVisible, setIsVisible] = useState(visible);
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const slideAnim = useRef(new Animated.Value(position === 'top' ? -100 : 100)).current;

  useEffect(() => {
    if (visible) {
      setIsVisible(true);
      // Animate in
      Animated.parallel([
        Animated.timing(fadeAnim, {
          toValue: 1,
          duration: 300,
          useNativeDriver: true,
        }),
        Animated.spring(slideAnim, {
          toValue: 0,
          tension: 50,
          friction: 7,
          useNativeDriver: true,
        }),
      ]).start();

      // Auto dismiss
      if (duration > 0) {
        const timer = setTimeout(() => {
          hideToast();
        }, duration);
        return () => clearTimeout(timer);
      }
    } else {
      hideToast();
    }
  }, [visible, duration]);

  const hideToast = () => {
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 0,
        duration: 250,
        useNativeDriver: true,
      }),
      Animated.timing(slideAnim, {
        toValue: position === 'top' ? -100 : 100,
        duration: 250,
        useNativeDriver: true,
      }),
    ]).start(() => {
      setIsVisible(false);
      if (onClose) onClose();
    });
  };

  if (!isVisible && !visible) return null;

  const getTypeStyles = () => {
    switch (type) {
      case 'success':
        return {
          backgroundColor: Colors.success500,
          iconColor: Colors.white,
          iconName: icon || 'check-circle',
        };
      case 'error':
        return {
          backgroundColor: Colors.error500,
          iconColor: Colors.white,
          iconName: icon || 'x-circle',
        };
      case 'warning':
        return {
          backgroundColor: Colors.warning500,
          iconColor: Colors.white,
          iconName: icon || 'alert-triangle',
        };
      case 'info':
      default:
        return {
          backgroundColor: Colors.info500,
          iconColor: Colors.white,
          iconName: icon || 'info',
        };
    }
  };

  const typeStyles = getTypeStyles();

  return (
    <Animated.View
      style={[
        styles.container,
        {
          top: position === 'top' ? 0 : undefined,
          bottom: position === 'bottom' ? 0 : undefined,
          opacity: fadeAnim,
          transform: [{ translateY: slideAnim }],
        },
        style,
      ]}
      pointerEvents="box-none">
      <SafeAreaView edges={position === 'top' ? ['top'] : ['bottom']}>
        <View style={[styles.toast, { backgroundColor: typeStyles.backgroundColor }]}>
          <View style={styles.content}>
            {typeStyles.iconName && (
              <FeatherIcon
                name={typeStyles.iconName}
                size={20}
                color={typeStyles.iconColor}
                style={styles.icon}
              />
            )}
            <Text style={[styles.message, textStyle]} numberOfLines={3}>
              {message}
            </Text>
          </View>
          {action && (
            <TouchableOpacity onPress={action} style={styles.actionButton}>
              <Text style={styles.actionText}>{actionLabel || 'Action'}</Text>
            </TouchableOpacity>
          )}
          <TouchableOpacity onPress={hideToast} style={styles.closeButton}>
            <FeatherIcon name="x" size={18} color={Colors.white} />
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    </Animated.View>
  );
};

// Toast Manager untuk global usage
class ToastManager {
  constructor() {
    this.toasts = [];
    this.listeners = [];
    this.toastId = 0;
  }

  subscribe(listener) {
    this.listeners.push(listener);
    return () => {
      this.listeners = this.listeners.filter((l) => l !== listener);
    };
  }

  notify() {
    this.listeners.forEach((listener) => listener(this.toasts));
  }

  show(message, options = {}) {
    const id = ++this.toastId;
    const toast = {
      id,
      message,
      type: options.type || 'info',
      position: options.position || 'top',
      duration: options.duration !== undefined ? options.duration : 3000,
      icon: options.icon,
      action: options.action,
      actionLabel: options.actionLabel,
      style: options.style,
      textStyle: options.textStyle,
    };

    this.toasts.push(toast);
    this.notify();

    // Auto remove after duration
    if (toast.duration > 0) {
      setTimeout(() => {
        this.hide(id);
      }, toast.duration);
    }

    return id;
  }

  hide(id) {
    this.toasts = this.toasts.filter((toast) => toast.id !== id);
    this.notify();
  }

  success(message, options = {}) {
    return this.show(message, { ...options, type: 'success' });
  }

  error(message, options = {}) {
    return this.show(message, { ...options, type: 'error' });
  }

  warning(message, options = {}) {
    return this.show(message, { ...options, type: 'warning' });
  }

  info(message, options = {}) {
    return this.show(message, { ...options, type: 'info' });
  }

  clear() {
    this.toasts = [];
    this.notify();
  }
}

// Global Toast Manager instance
toastManager = new ToastManager();

// Global Toast Container Component
const ToastContainer = () => {
  const [toasts, setToasts] = useState([]);

  useEffect(() => {
    const unsubscribe = toastManager.subscribe(setToasts);
    return unsubscribe;
  }, []);

  // Group toasts by position
  const toastsByPosition = {
    top: toasts.filter((t) => t.position === 'top'),
    bottom: toasts.filter((t) => t.position === 'bottom'),
  };

  return (
    <>
      {toastsByPosition.top.map((toast) => (
        <Toast
          key={toast.id}
          visible={true}
          message={toast.message}
          type={toast.type}
          position={toast.position}
          duration={0} // Managed by ToastManager
          icon={toast.icon}
          action={toast.action}
          actionLabel={toast.actionLabel}
          style={toast.style}
          textStyle={toast.textStyle}
          onClose={() => toastManager.hide(toast.id)}
        />
      ))}
      {toastsByPosition.bottom.map((toast) => (
        <Toast
          key={toast.id}
          visible={true}
          message={toast.message}
          type={toast.type}
          position={toast.position}
          duration={0} // Managed by ToastManager
          icon={toast.icon}
          action={toast.action}
          actionLabel={toast.actionLabel}
          style={toast.style}
          textStyle={toast.textStyle}
          onClose={() => toastManager.hide(toast.id)}
        />
      ))}
    </>
  );
};

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    left: 0,
    right: 0,
    zIndex: 9999,
    paddingHorizontal: 16,
  },
  toast: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    marginVertical: 8,
    minHeight: 48,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.25,
    shadowRadius: 4,
    elevation: 5,
  },
  content: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
  },
  icon: {
    marginRight: 12,
  },
  message: {
    flex: 1,
    fontSize: 14,
    color: Colors.white,
    fontWeight: '500',
    lineHeight: 20,
  },
  actionButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    marginRight: 8,
    borderRadius: 4,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
  },
  actionText: {
    fontSize: 13,
    color: Colors.white,
    fontWeight: '600',
  },
  closeButton: {
    padding: 4,
    marginLeft: 4,
  },
});

// Export Toast component and manager
export default Toast;
export { Toast, ToastContainer, toastManager };


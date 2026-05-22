import React from 'react';
import {
  useWindowDimensions,
  View,
  Text,
  ActivityIndicator,
  StyleSheet,
} from 'react-native';
  const Loader = ({
    color = "#fff",
    visible = false
   }) => {
  return (
    visible && (
        <View>
          <ActivityIndicator  size="small" color={color} />
        </View>

    )
  );
};

export default Loader;

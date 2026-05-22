import React from "react";
import { View, Text, StyleSheet, JsonView } from "NexaJS";

const Halaman = ({ route }) => {
  return (
    <View style={styles.container}>
      <View style={styles.contentContainer}>
        <Text style={styles.heading}>Contoh Screen Halaman</Text>
        <Text style={styles.subheading}>
          Ini hanya screen contoh sederhana untuk navigasi.
        </Text>
        {route?.params && (
          <Text>
            <JsonView data={route.params} 
            maxHeight={420}
            dark={false}
            collapsible
            />
          </Text>
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#fff",
  },
  contentContainer: {
    padding: 16,
  },
  heading: {
    fontSize: 24,
    fontWeight: "bold",
    marginBottom: 10,
  },
  subheading: {
    fontSize: 14,
    color: "#333",
    marginBottom: 12,
  },
  params: {
    fontSize: 14,
    color: "#666",
  },
});

export default Halaman;

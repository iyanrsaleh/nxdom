import {
  View,
  StyleSheet,
  useNavigation,
  assetsImage,
  Image,
  fs,
  Text,
  Buttons,
} from "NexaJS";

const screens = () => {
  const navigation = useNavigation();
  const assetColor = {
    backgroundColor: "#211E1F",
    buttonColor: "#211E1F",
    buttonTextColor: "#FFFFFF"
  };
  return (
    <View style={styles.container}>
      <View style={styles.centerBlock}>
        <View style={styles.logoWrap}>
          <Image source={assetsImage.get("nexajs")} style={styles.logo} />
        </View>
        <View style={styles.titleSection}>
          <Text style={[fs["4xl"], styles.customAvatar, fs.semibold]}>
            Nxdom  <Text style={[fs["xs"]]}>v.1.0.0</Text>
          </Text>
          <Text style={[fs.semibold, styles.subtitle]}>
            Nusantara eXtreme Development Object Model
          </Text>
        </View>
        <View style={styles.buttonWrap}>
          <Buttons 
            label="Screen Halaman"
            background={assetColor.buttonColor}
            txColor={assetColor.buttonTextColor}
            border={8}
            padding={100}
            vertical={6}
            onPress={() =>
              navigation.navigate("halaman", {
                type: "halaman",
                count: 5,
                messages: ["Pesan 1", "Pesan 2", "Pesan 3"],
              })
            }
          />
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 20,
    justifyContent: "center",
    alignItems: "center",
    backgroundColor: "#fff",
  },
  centerBlock: {
    width: "100%",
    maxWidth: 360,
    gap: 16,
    alignItems: "center",
  },
  titleSection: {
    alignItems: "center",
  },
  customAvatar: {
    paddingTop: 0,
    textAlign: "center",
    width: "100%",
  },
  subtitle: {
    textAlign: "center",
    width: "100%",
  },
  logoWrap: {
    width: "100%",
    alignItems: "center",
    justifyContent: "center",
  },
  logo: {
    width: 80,
    height: 80,
    borderRadius: 24,
    alignSelf: "center",
  },
  buttonWrap: {
    width: "100%",
    alignItems: "center",
  },
});

export default screens;
